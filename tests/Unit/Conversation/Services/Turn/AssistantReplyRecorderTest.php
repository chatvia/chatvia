<?php

use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Jobs\StoreMessageJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * A completed answer is stored and announced, so it counts against the
 * workspace's monthly message limit; a guardrail refusal is stored but never
 * counted.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
    Bus::fake();
});

function recorderContext(): ChatContext
{
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
    ]);

    $request = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: Llm::factory()->create(), message: 'Summarise the PDF'),
        externalSessionId: $session->external_id,
        conversationId: $conversation->external_id,
    );

    $context = new ChatContext(
        requestData: $request,
        payload: new UserTurnData(
            external_session_id: $session->external_id,
            external_conversation_id: $conversation->external_id,
            external_message_id: (string) Str::uuid(),
            model: 'm',
            message: 'Summarise the PDF',
            sent_at: now(),
        ),
        llmMessages: [],
        userMessageId: null,
        assistantMessageId: (string) Str::uuid(),
    );
    $context->appendStreamedMessage('Here is the summary.');

    return $context;
}

function recorderHistory(ChatContext $context): array
{
    $request = $context->requestData;

    return app(ChatMemory::class)
        ->withScope(new ChatScopeData($request->agent, $request->externalSessionId, $request->conversationId))
        ->getMessagesForConversation();
}

it('stores a completed answer and announces it', function (): void {
    Event::fake([AssistantReplyCompleted::class]);
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    Event::assertDispatched(AssistantReplyCompleted::class, fn (AssistantReplyCompleted $event): bool => $event->workspaceId === $context->requestData->agent->workspace_id
        && $event->assistantMessageId === $context->assistantMessageId);

    expect(recorderHistory($context)[0]['content'])->toBe('Here is the summary.')
        ->and(recorderHistory($context)[0]['role'])->toBe('assistant');
});

it('counts the reply against the workspace monthly message limit', function (): void {
    $context = recorderContext();
    app(SetMessageLimitAction::class)->execute($context->requestData->agent->workspace, 10);

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    expect(MessageLimit::query()->where('workspace_id', $context->requestData->agent->workspace_id)->value('messages_used'))->toBe(1);
});

it('stores a refusal without counting it', function (): void {
    Event::fake([AssistantReplyCompleted::class]);
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordRefusal($context);

    Event::assertNotDispatched(AssistantReplyCompleted::class);
    expect(recorderHistory($context))->toHaveCount(1);
});

function recorderSource(?float $relevance): RetrievedSourceData
{
    return new RetrievedSourceData(
        id: 11,
        external_id: 'chunk-11',
        content: 'Returns are free.',
        relevance: $relevance,
        label: 'Returns',
        kind: SourceKind::Website,
        url: 'https://shop.test/returns',
        page: null,
        sourceable_type: 'source_website',
        sourceable_id: 7,
    );
}

it('stores the knowledge the answer was given and whether it was found', function (?float $relevance, ?bool $found): void {
    $context = recorderContext();
    $context->setSources(collect([recorderSource($relevance)]));
    $context->setConfidenceScore($relevance);

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    Bus::assertDispatched(StoreMessageJob::class, function (StoreMessageJob $job) use ($found): bool {
        $turn = $job->payload;

        return $turn instanceof AssistantTurnData
            && $turn->has_llm_found_answer === $found
            && count($turn->sources) === 1
            && $turn->sources[0]->rank === 0
            && $turn->sources[0]->chunk_id === 11
            && $turn->sources[0]->label === 'Returns';
    });
})->with([
    'checked and relevant' => [0.82, true],
    'used without a check' => [null, null],
]);

it('records an answer given no knowledge as not found', function (): void {
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload instanceof AssistantTurnData && $job->payload->has_llm_found_answer === false);
});

it('remembers only what the clients may see, for a client that shows sources', function (): void {
    $context = recorderContext();
    $context->requestData->streamSources = true;
    $context->setSources(collect([recorderSource(0.82)]));

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    $request = $context->requestData;
    $sources = app(ChatMemory::class)
        ->withScope(new ChatScopeData($request->agent, $request->externalSessionId, $request->conversationId))
        ->getSources();

    expect($sources[$context->assistantMessageId])->toBe([['id' => 'chunk-11', 'label' => 'Returns', 'kind' => 'website', 'url' => 'https://shop.test/returns', 'page' => null]]);
});

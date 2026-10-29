<?php

use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\MessageSourceData;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Jobs\StoreMessageJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Support\Conversation\TestChatContext;

/**
 * A completed answer is stored and announced, so it counts against the
 * workspace's monthly message limit; a guardrail refusal is stored but never
 * counted. Every chunk the answer was given is stored, while the clients are
 * shown one source per place.
 */
beforeEach(function (): void {
    Bus::fake();
});

function recorderContext(): ChatContext
{
    $conversation = Conversation::factory()->hostedChat()->create();

    $context = TestChatContext::make(
        agent: $conversation->agent,
        message: 'Summarise the PDF',
        llmMessages: [],
        externalSessionId: $conversation->session->external_id,
        conversationId: $conversation->external_id,
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

it('stores a completed answer and counts it against the workspace monthly message limit', function (): void {
    $context = recorderContext();
    app(SetMessageLimitAction::class)->execute($context->requestData->agent->workspace, 10);

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    expect(recorderHistory($context)[0]->content)->toBe('Here is the summary.')
        ->and(recorderHistory($context)[0]->role)->toBe(MessageRole::Assistant)
        ->and(MessageLimit::query()->where('workspace_id', $context->requestData->agent->workspace_id)->value('messages_used'))->toBe(1);
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

it('stores every chunk the answer was given as found, but remembers one source per place for the clients', function (): void {
    $context = recorderContext();
    $context->requestData->streamSources = true;
    $second = recorderSource(0.74);
    $second->id = 12;
    $second->external_id = 'chunk-12';
    $context->setSources(collect([recorderSource(0.82), $second]));
    $context->setConfidenceScore(0.82);

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    $request = $context->requestData;
    $sources = app(ChatMemory::class)
        ->withScope(new ChatScopeData($request->agent, $request->externalSessionId, $request->conversationId))
        ->getSources();
    expect($sources[$context->assistantMessageId])->toHaveCount(1)
        ->and($sources[$context->assistantMessageId][0]->toArray())->toMatchArray(['id' => 'chunk-11', 'passages' => 2]);
    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload instanceof AssistantTurnData
        && $job->payload->has_llm_found_answer === true
        && array_map(static fn (MessageSourceData $source): ?int => $source->chunk_id, $job->payload->sources) === [11, 12]);
});

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
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Spectra\Support\Tracking\TraceContext;
use Tests\Support\Conversation\TestChatContext;
use Tests\Support\Conversation\Tooling\ToolLoop;

/**
 * A completed answer is stored and announced, so it counts against the
 * workspace's monthly message limit; a guardrail refusal is stored but never
 * counted.
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

it('stores a completed answer and announces it', function (): void {
    Event::fake([AssistantReplyCompleted::class]);
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    Event::assertDispatched(AssistantReplyCompleted::class, fn (AssistantReplyCompleted $event): bool => $event->workspaceId === $context->requestData->agent->workspace_id
        && $event->assistantMessageId === $context->assistantMessageId);

    expect(recorderHistory($context)[0]->content)->toBe('Here is the summary.')
        ->and(recorderHistory($context)[0]->role)->toBe(MessageRole::Assistant);
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

it('stamps the reply with the Spectra trace of the turn', function (): void {
    config(['spectra.enabled' => true]);
    Context::addHidden(TraceContext::TRACE_ID, '6f1f9f58-4f42-4af0-9d4e-0b1a1f1c2d3e');
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload instanceof AssistantTurnData
        && $job->payload->trace_id === '6f1f9f58-4f42-4af0-9d4e-0b1a1f1c2d3e');
});

it('stores no trace while Spectra is not recording', function (): void {
    config(['spectra.enabled' => false]);
    Context::addHidden(TraceContext::TRACE_ID, '6f1f9f58-4f42-4af0-9d4e-0b1a1f1c2d3e');
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload instanceof AssistantTurnData
        && $job->payload->trace_id === null);
});

it('stores no trace for a turn that made no AI call', function (): void {
    config(['spectra.enabled' => true]);
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload instanceof AssistantTurnData
        && $job->payload->trace_id === null);
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

    expect(collect($sources[$context->assistantMessageId])->toArray())->toBe([['id' => 'chunk-11', 'label' => 'Returns', 'kind' => 'website', 'url' => 'https://shop.test/returns', 'page' => null, 'passages' => 1, 'blocks' => [1], 'excerpt' => 'Returns are free.']]);
});

it('remembers the chunks of one source as one source, but stores every chunk', function (): void {
    $context = recorderContext();
    $context->requestData->streamSources = true;
    $second = recorderSource(0.74);
    $second->id = 12;
    $second->external_id = 'chunk-12';
    $context->setSources(collect([recorderSource(0.82), $second]));

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    $request = $context->requestData;
    $sources = app(ChatMemory::class)
        ->withScope(new ChatScopeData($request->agent, $request->externalSessionId, $request->conversationId))
        ->getSources();

    expect($sources[$context->assistantMessageId])->toHaveCount(1)
        ->and($sources[$context->assistantMessageId][0]->toArray())->toMatchArray(['id' => 'chunk-11', 'passages' => 2]);

    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload instanceof AssistantTurnData
        && array_map(static fn (MessageSourceData $source): ?int => $source->chunk_id, $job->payload->sources) === [11, 12]);
});

it('keeps the step chain in the message metadata, so it comes back with the history', function (): void {
    Event::fake([AssistantReplyCompleted::class]);
    $context = recorderContext();
    $context->addTextStep('Let me read the PDF.');
    $context->addToolStep(ToolLoop::pendingCall('read_pdf', gated: false));

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    expect(recorderHistory($context)[0]->metadata['steps'])->toBe([
        ['kind' => 'text', 'text' => 'Let me read the PDF.', 'label' => null, 'tool_kind' => null, 'icon_url' => null],
        ['kind' => 'tool', 'text' => null, 'label' => 'Read Pdf', 'tool_kind' => 'function', 'icon_url' => null],
    ]);
    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload instanceof AssistantTurnData
        && count($job->payload->steps) === 2);
});

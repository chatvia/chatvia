<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\Support\Conversation\NoToolSelector;
use Tests\Support\Conversation\ScriptedToolLoopRunner;
use Tests\Support\Conversation\TestChatContext;
use Tests\Support\Llm\CannedLlmProvider;
use Tests\Support\Llm\FakeLlmProviders;

/**
 * LlmSource streams a turn in the shared envelope: the start event, the
 * sources, the done event, the mapping of exceptions to error events, and the
 * partial save and replay entry on a client disconnect. The model streaming
 * and the tool loop run inside ToolLoopRunner; these tests script it and
 * check what LlmSource does around it.
 */
function lsReplyRecorder(): AssistantReplyRecorder
{
    return new class extends AssistantReplyRecorder
    {
        public function __construct() {}

        public function recordCompletedReply(ChatContext $context): void {}
    };
}

/**
 * A turn of an agent with its guardrails switched off, so the real output
 * guard returns the answer unchanged without a classifier call.
 */
function lsContext(bool $streamSources = false): ChatContext
{
    $agent = Agent::factory()->create();
    $agent->setSetting('enabled', false, 'guardrails');

    return TestChatContext::make(agent: $agent, streamSources: $streamSources);
}

function lsSource(ToolLoopRunner $runner): LlmSource
{
    return new LlmSource(
        providers: new FakeLlmProviders(new CannedLlmProvider),
        replyRecorder: lsReplyRecorder(),
        dedupe: new RequestDedupeStore,
        toolSelector: new NoToolSelector,
        runner: $runner,
        guardrails: app(GuardrailService::class),
        guardrailRecorder: app(GuardrailRecorder::class),
    );
}

/** @return list<StreamEvent> */
function lsEvents(ToolLoopRunner $runner, ChatContext $context): array
{
    return iterator_to_array(lsSource($runner)->events($context, [['role' => 'user', 'content' => 'Hi']]), preserve_keys: false);
}

/** @param  list<StreamEvent>  $events */
function lsTypes(array $events): array
{
    return array_map(static fn (StreamEvent $e): EventType => $e->type, $events);
}

function lsRetrievedSource(): RetrievedSourceData
{
    return new RetrievedSourceData(
        id: 11,
        external_id: 'chunk-11',
        content: 'Returns are free within 30 days.',
        relevance: 0.82,
        label: 'Returns policy',
        kind: SourceKind::Website,
        url: 'https://shop.test/returns',
        page: null,
        sourceable_type: 'source_website',
        sourceable_id: 7,
    );
}

beforeEach(function (): void {
    Cache::flush(); // RequestDedupeStore reads and writes through Cache
    Log::spy();
});

it('yields start, delegates to the runner, and finishes with done on a normal turn', function (): void {
    $context = lsContext();

    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        yield StreamEvent::delta($ctx->assistantMessageId, 'Hello, ');
        yield StreamEvent::delta($ctx->assistantMessageId, 'world.');
        $ctx->appendStreamedMessage('Hello, world.');

        return false; // not paused: a final answer was streamed
    });

    $events = lsEvents($runner, $context);

    expect(lsTypes($events))->toBe([EventType::Start, EventType::Delta, EventType::Delta, EventType::Done])
        ->and($events[0]->data['assistant_message_id'])->toBe($context->assistantMessageId)
        ->and($events[0]->data['replayed'])->toBeFalse()
        ->and(end($events)->data['reason'])->toBe('complete');
});

it('sends the sources of the answer before done when the client shows sources', function (): void {
    $context = lsContext(streamSources: true);
    $context->setSources(collect([lsRetrievedSource()]));

    $events = lsEvents(ScriptedToolLoopRunner::answering('Returns are free.'), $context);
    $sources = collect($events)->first(fn (StreamEvent $e): bool => $e->type === EventType::Sources);

    expect(lsTypes($events))->toBe([EventType::Start, EventType::Delta, EventType::Sources, EventType::Done])
        ->and(collect($sources->data['sources'])->toArray())->toBe([[
            'id' => 'chunk-11',
            'label' => 'Returns policy',
            'kind' => 'website',
            'url' => 'https://shop.test/returns',
            'page' => null,
            'passages' => 1,
        ]])
        ->and(collect(app(RequestDedupeStore::class)->find($context->requestData->requestId)->sources)->toArray())
        ->toBe(collect($sources->data['sources'])->toArray());
});

it('sends no sources event when the client does not show sources', function (): void {
    $context = lsContext();
    $context->setSources(collect([lsRetrievedSource()]));

    $events = lsEvents(ScriptedToolLoopRunner::answering('Returns are free.'), $context);

    expect(lsTypes($events))->toBe([EventType::Start, EventType::Delta, EventType::Done])
        ->and(app(RequestDedupeStore::class)->find($context->requestData->requestId)->sources)->toBe([]);
});

it('yields only the start event and returns early when the runner pauses for approval', function (): void {
    $context = lsContext();

    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        // The runner emits its own awaiting_approval done before returning
        // paused; LlmSource adds nothing after that.
        yield StreamEvent::done($ctx->assistantMessageId, 'awaiting_approval');

        return true;
    });

    $events = lsEvents($runner, $context);

    // start + the runner's awaiting_approval done; no sources, no trailing "complete" done
    expect(lsTypes($events))->toBe([EventType::Start, EventType::Done])
        ->and($events[1]->data['reason'])->toBe('awaiting_approval');
});

it('on ClientDisconnected does not surface an error and keeps the partial answer for replay', function (): void {
    $context = lsContext();

    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        yield StreamEvent::delta($ctx->assistantMessageId, 'partial reply');
        $ctx->appendStreamedMessage('partial reply');
        throw new ClientDisconnected;
    });

    $events = lsEvents($runner, $context);

    // No `error`, no trailing `done`: the client is gone, so nothing is sent.
    expect(lsTypes($events))->toBe([EventType::Start, EventType::Delta]);

    // The partial answer is remembered, so a quick retry gets the same
    // assistant_message_id without a second upstream call.
    $cached = app(RequestDedupeStore::class)->find($context->requestData->requestId);
    expect($cached)->not->toBeNull()
        ->and($cached->content)->toBe('partial reply')
        ->and($cached->assistant_message_id)->toBe($context->assistantMessageId);
});

it('on UpstreamStreamClosed surfaces a user-friendly upstream-unavailable error and done(error)', function (): void {
    $context = lsContext();

    $runner = new ScriptedToolLoopRunner(function (ChatContext $ctx): Generator {
        yield StreamEvent::delta($ctx->assistantMessageId, 'partial');
        throw new UpstreamStreamClosed;
    });

    $events = lsEvents($runner, $context);

    expect(lsTypes($events))->toBe([EventType::Start, EventType::Delta, EventType::Error, EventType::Done])
        ->and($events[2]->data['code'])->toBe(ErrorCode::UpstreamUnavailable->value)
        ->and($events[3]->data['reason'])->toBe('error');
});

it('on an unexpected Throwable surfaces an internal error and done(error)', function (): void {
    $context = lsContext();

    $runner = new ScriptedToolLoopRunner(function (): Generator {
        yield from [];

        throw new RuntimeException('boom');
    });

    $events = lsEvents($runner, $context);

    expect(lsTypes($events))->toBe([EventType::Start, EventType::Error, EventType::Done])
        ->and($events[1]->data['code'])->toBe(ErrorCode::Internal->value)
        ->and($events[2]->data['reason'])->toBe('error');
});

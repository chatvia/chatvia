<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\Llm;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\FakeLlmProviders;

/**
 * LlmSource is the SSE-turn orchestrator: it owns the envelope (start/sources/done),
 * the trace_id binding, the exception → error-event mapping, and the partial-save +
 * replay-remember on ClientDisconnected. The actual model streaming + tool loop runs
 * inside ToolLoopRunner — these tests fake ToolLoopRunner to script its return /
 * yields and verify LlmSource's surrounding behavior.
 */
function noopReplyRecorder(): AssistantReplyRecorder
{
    return new class extends AssistantReplyRecorder
    {
        public function __construct() {}

        public function recordCompletedReply(ChatContext $context): void
        {
            // intentional no-op for tests
        }
    };
}

function noopLlmProvider(): LlmProviderInterface
{
    return new class implements LlmProviderInterface
    {
        public function stream(string $model, array $messages, array $settings = []): ResponseInterface
        {
            throw new RuntimeException('stream() not reached — runner is faked');
        }

        public function client(): PendingRequest
        {
            throw new RuntimeException('client() not used in LlmSource tests');
        }

        public function completion(array $data): never
        {
            throw new RuntimeException('completion() not used in LlmSource tests');
        }

        public function headers(): array
        {
            return [];
        }

        public function getGenerationCost(string $generationId): ?array
        {
            return null;
        }

        public function getCompletionEndpoint(): string
        {
            return '';
        }

        public function supportsCostTracking(): bool
        {
            return false;
        }
    };
}

function noopToolSelector(): ToolSelector
{
    return new class extends ToolSelector
    {
        public function __construct() {}

        public function selectForTurn(Agent $agent, string $message, ?string $conversationId): array
        {
            return [];
        }
    };
}

/**
 * Build a ToolLoopRunner subclass whose `run()` is scripted: the closure
 * receives the assistantMessageId so its generator can yield events tagged
 * to that id; its eventual return value is whether the turn paused.
 */
function fakeToolLoopRunner(Closure $scripted): ToolLoopRunner
{
    return new class($scripted) extends ToolLoopRunner
    {
        public function __construct(private readonly Closure $scripted)
        {
            // bypass parent constructor — no dependencies needed for fakes
        }

        public function run(
            ChatContext $context,
            LlmProviderInterface $provider,
            string $modelName,
            array &$llmMessages,
            array $toolDefinitions,
            array $extraSettings = [],
            int $startIteration = 0,
            bool $captureToolCallsOnly = false,
        ): Generator {
            return yield from ($this->scripted)($context, $llmMessages);
        }
    };
}

function makeChatContext(Agent $agent, Llm $llm): ChatContext
{
    $requestData = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: $llm, message: 'Hello'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
        requestId: 'req_'.Str::random(12),
    );

    $payload = UserTurnData::from([
        'external_session_id' => $requestData->externalSessionId,
        'external_conversation_id' => $requestData->conversationId,
        'external_message_id' => (string) Str::uuid(),
        'model' => $llm->external_name,
        'message' => 'Hello',
        'sent_at' => now(),
    ]);

    return new ChatContext(
        requestData: $requestData,
        payload: $payload,
        llmMessages: [['role' => 'user', 'content' => 'Hello']],
        userMessageId: (string) Str::uuid(),
        assistantMessageId: (string) Str::uuid(),
    );
}

function makeLlmSource(ToolLoopRunner $runner): LlmSource
{
    return new LlmSource(
        providers: new FakeLlmProviders(noopLlmProvider()),
        replyRecorder: noopReplyRecorder(),
        dedupe: new RequestDedupeStore,
        toolSelector: noopToolSelector(),
        runner: $runner,
        // Real services; the factory agents have guardrails off by default, so
        // inspectOutput() is a clean no-op (no classifier call, no events).
        guardrails: app(GuardrailService::class),
        guardrailRecorder: app(GuardrailRecorder::class),
    );
}

beforeEach(function (): void {
    Cache::flush(); // RequestDedupeStore reads/writes through Cache
    Log::spy();
});

it('yields start, delegates to the runner, and finishes with sources + done on a normal turn', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $context = makeChatContext($agent, $llm);

    $runner = fakeToolLoopRunner(function (ChatContext $ctx) {
        yield StreamEvent::delta($ctx->assistantMessageId, 'Hello, ');
        yield StreamEvent::delta($ctx->assistantMessageId, 'world.');
        $ctx->appendStreamedMessage('Hello, world.');

        return false; // not paused — a final answer was streamed
    });

    $events = iterator_to_array(makeLlmSource($runner)->events($context, [['role' => 'user', 'content' => 'Hi']]), preserve_keys: false);

    $types = array_map(fn (StreamEvent $e): EventType => $e->type, $events);

    expect($types)->toBe([EventType::Start, EventType::Delta, EventType::Delta, EventType::Done])
        ->and($events[0]->data['assistant_message_id'])->toBe($context->assistantMessageId)
        ->and($events[0]->data['replayed'])->toBeFalse()
        ->and(end($events)->data['reason'])->toBe('complete');
});

it('yields only the start event and returns early when the runner pauses for approval', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $context = makeChatContext($agent, $llm);

    $runner = fakeToolLoopRunner(function (ChatContext $ctx) {
        // Runner emits its own awaiting_approval + done internally before returning
        // paused=true. LlmSource doesn't add anything more after that.
        yield StreamEvent::done($ctx->assistantMessageId, 'awaiting_approval');

        return true; // paused — LlmSource must `return;` immediately
    });

    $events = iterator_to_array(makeLlmSource($runner)->events($context, []), preserve_keys: false);

    $types = array_map(fn (StreamEvent $e): EventType => $e->type, $events);

    // start + runner's awaiting_approval done; NO sources / NO trailing "complete" done
    expect($types)->toBe([EventType::Start, EventType::Done])
        ->and($events[1]->data['reason'])->toBe('awaiting_approval');
});

it('on ClientDisconnected does not surface an error and persists for replay (partial)', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $context = makeChatContext($agent, $llm);

    $runner = fakeToolLoopRunner(function (ChatContext $ctx) {
        yield StreamEvent::delta($ctx->assistantMessageId, 'partial reply');
        $ctx->appendStreamedMessage('partial reply');
        throw new ClientDisconnected;
    });

    $events = iterator_to_array(makeLlmSource($runner)->events($context, []), preserve_keys: false);

    $types = array_map(fn (StreamEvent $e): EventType => $e->type, $events);

    // No `error`, no trailing `done` — caller already disconnected, nothing to send.
    expect($types)->toBe([EventType::Start, EventType::Delta]);

    // Replay-remember on the partial buffer so a quick reconnect/retry can pick up
    // the same assistant_message_id without a duplicate upstream call.
    $cached = app(RequestDedupeStore::class)->find($context->requestData->requestId);
    expect($cached)->not->toBeNull()
        ->and($cached->content)->toBe('partial reply')
        ->and($cached->assistant_message_id)->toBe($context->assistantMessageId);
});

it('on UpstreamStreamClosed surfaces a user-friendly upstream-unavailable error and done(error)', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $context = makeChatContext($agent, $llm);

    $runner = fakeToolLoopRunner(function (ChatContext $ctx) {
        yield StreamEvent::delta($ctx->assistantMessageId, 'partial');
        throw new UpstreamStreamClosed;
    });

    $events = iterator_to_array(makeLlmSource($runner)->events($context, []), preserve_keys: false);

    $types = array_map(fn (StreamEvent $e): EventType => $e->type, $events);

    expect($types)->toBe([EventType::Start, EventType::Delta, EventType::Error, EventType::Done]);

    $errorEvent = $events[2];
    expect($errorEvent->data['code'])->toBe(ErrorCode::UpstreamUnavailable->value);

    $doneEvent = $events[3];
    expect($doneEvent->data['reason'])->toBe('error');
});

it('on an unexpected Throwable surfaces an internal error and done(error)', function (): void {
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();
    $context = makeChatContext($agent, $llm);

    $runner = fakeToolLoopRunner(function () {
        if (false) {
            yield;
        }
        throw new RuntimeException('boom');
    });

    $events = iterator_to_array(makeLlmSource($runner)->events($context, []), preserve_keys: false);

    $types = array_map(fn (StreamEvent $e): EventType => $e->type, $events);

    expect($types)->toBe([EventType::Start, EventType::Error, EventType::Done]);

    expect($events[1]->data['code'])->toBe(ErrorCode::Internal->value);
    expect($events[2]->data['reason'])->toBe('error');
});

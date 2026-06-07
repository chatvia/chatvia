<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Data\StreamRequestMessageData;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Transformers\StreamTransformer;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalPolicy;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Conversation\Services\Tooling\Dispatcher\ToolDispatcherInterface;
use Domain\Conversation\Services\Tooling\Dispatcher\ToolDispatchResult;
use Domain\Conversation\Services\Tooling\Registry\ToolDisplayResolver;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\Llm;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Spectra\Facades\Spectra;

/**
 * ToolLoopRunner is the provider-agnostic agentic loop: it streams a completion,
 * drains tool_calls, gates them through ToolApprovalCheckpoint, dispatches the
 * approved ones, and enforces the iteration cap. It is the unit where a subtle
 * bug is most damaging — a regressed approval gate could auto-run a write tool
 * the user never approved, and a broken cap could loop the model unbounded.
 *
 * Its collaborators are all interfaces/overridable classes, so we fake them and
 * drive the real run()/applyPendingCalls() bodies. The Spectra cost tracker is
 * faked; the ToolApprovalStore is real so we can assert the checkpoint is
 * actually persisted.
 */

// --- fakes -------------------------------------------------------------------

function tlrProvider(): LlmProviderInterface
{
    return new class implements LlmProviderInterface
    {
        public function stream(string $model, array $messages, array $settings = []): ResponseInterface
        {
            // The fake transformer ignores the upstream response; return a stub.
            return new Response;
        }

        public function client(): PendingRequest
        {
            throw new RuntimeException('client() not used in ToolLoopRunner tests');
        }

        public function completion(array $data): void
        {
            throw new RuntimeException('completion() not used in ToolLoopRunner tests');
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

/**
 * A dispatcher that records every call so tests can assert what ran (and what
 * never did). Returns a deterministic "result of {name}" string.
 */
function tlrDispatcher(): ToolDispatcherInterface
{
    return new class implements ToolDispatcherInterface
    {
        /** @var array<int, array<string, mixed>> */
        public array $calls = [];

        public function dispatch(Agent $agent, string $name, string $arguments, ?string $conversationExternalId = null): ToolDispatchResult
        {
            $this->calls[] = ['name' => $name, 'arguments' => $arguments, 'conversation' => $conversationExternalId];

            return ToolDispatchResult::text("result of {$name}");
        }
    };
}

function tlrDisplayResolver(): ToolDisplayResolver
{
    return new class extends ToolDisplayResolver
    {
        public function __construct() {}

        public function resolve(Agent $agent, string $toolName): array
        {
            return ['kind' => 'function', 'label' => $toolName, 'icon_url' => null, 'brand_color' => null];
        }
    };
}

function tlrApprovalPolicy(bool $requires): ToolApprovalPolicy
{
    return new class($requires) extends ToolApprovalPolicy
    {
        public function __construct(private bool $requires) {}

        public function requiresApproval(Agent $agent, string $toolName): bool
        {
            return $this->requires;
        }
    };
}

/**
 * Build a transformer that runs $onTransform($context) — the hook tests use to
 * push tool calls into the context and/or yield deltas, standing in for what
 * OpenAiCompatibleTransformer does off a live stream.
 */
function tlrTransformer(Closure $onTransform): StreamTransformer
{
    return new class($onTransform) implements StreamTransformer
    {
        public function __construct(private Closure $onTransform) {}

        public function transform(ResponseInterface $upstream, ChatContext $context): Generator
        {
            return yield from ($this->onTransform)($context);
        }
    };
}

function tlrNoopTransformer(): StreamTransformer
{
    return tlrTransformer(function (ChatContext $context) {
        yield from [];
    });
}

function tlrRunner(
    StreamTransformer $transformer,
    ToolDispatcherInterface $dispatcher,
    ToolApprovalPolicy $policy,
    ?ToolApprovalStore $store = null,
): ToolLoopRunner {
    return new ToolLoopRunner(
        transformer: $transformer,
        dispatcher: $dispatcher,
        displayResolver: tlrDisplayResolver(),
        approvalPolicy: $policy,
        approvalStore: $store ?? new ToolApprovalStore,
    );
}

function tlrContext(Agent $agent, Llm $llm): ChatContext
{
    $requestData = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: $llm, message: 'Hello'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
        requestId: 'req_'.Str::random(12),
    );

    $payload = ChatInputPayload::from([
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

beforeEach(function (): void {
    Spectra::fake();
    config(['agent.tools.max_iterations' => 5]);
});

// --- applyPendingCalls: the approve / deny / auto semantics ------------------

it('injects the denied-result message and never dispatches a denied gated call', function (): void {
    $context = tlrContext(Agent::factory()->create(), Llm::factory()->create());
    $dispatcher = tlrDispatcher();
    $runner = tlrRunner(tlrNoopTransformer(), $dispatcher, tlrApprovalPolicy(true));

    $pendingCalls = [[
        'tool_call_id' => 'call_1',
        'name' => 'delete_everything',
        'arguments' => '{}',
        'requires_approval' => true,
        'kind' => 'function',
        'label' => 'Delete everything',
        'icon_url' => null,
        'brand_color' => null,
        'reason' => ToolLoopRunner::APPROVAL_REASON,
    ]];

    $llmMessages = [];
    $events = iterator_to_array(
        $runner->applyPendingCalls($context, $llmMessages, $pendingCalls, ['call_1' => 'deny']),
        preserve_keys: false,
    );

    expect($dispatcher->calls)->toBe([])      // the gated tool NEVER ran
        ->and($events)->toBe([])              // and emitted no start/end events
        ->and($llmMessages)->toHaveCount(1)
        ->and($llmMessages[0]['role'])->toBe('tool')
        ->and($llmMessages[0]['tool_call_id'])->toBe('call_1')
        ->and($llmMessages[0]['content'])->toBe(ToolLoopRunner::DENIED_RESULT);
});

it('dispatches an approved gated call and appends its tool result', function (): void {
    $context = tlrContext(Agent::factory()->create(), Llm::factory()->create());
    $dispatcher = tlrDispatcher();
    $runner = tlrRunner(tlrNoopTransformer(), $dispatcher, tlrApprovalPolicy(true));

    $pendingCalls = [[
        'tool_call_id' => 'call_1',
        'name' => 'get_weather',
        'arguments' => '{"city":"Dubai"}',
        'requires_approval' => true,
        'kind' => 'function',
        'label' => 'Get weather',
        'icon_url' => null,
        'brand_color' => null,
        'reason' => ToolLoopRunner::APPROVAL_REASON,
    ]];

    $llmMessages = [];
    iterator_to_array(
        $runner->applyPendingCalls($context, $llmMessages, $pendingCalls, ['call_1' => 'approve']),
        preserve_keys: false,
    );

    expect($dispatcher->calls)->toHaveCount(1)
        ->and($dispatcher->calls[0]['name'])->toBe('get_weather')
        ->and($llmMessages)->toHaveCount(1)
        ->and($llmMessages[0]['role'])->toBe('tool')
        ->and($llmMessages[0]['content'])->toBe('result of get_weather')
        ->and($context->toolCallNames)->toBe(['get_weather']);
});

it('runs an ungated (auto) call even without an approval decision', function (): void {
    $context = tlrContext(Agent::factory()->create(), Llm::factory()->create());
    $dispatcher = tlrDispatcher();
    $runner = tlrRunner(tlrNoopTransformer(), $dispatcher, tlrApprovalPolicy(false));

    $pendingCalls = [[
        'tool_call_id' => 'call_1',
        'name' => 'list_products',
        'arguments' => '{}',
        'requires_approval' => false,
        'kind' => 'function',
        'label' => 'List products',
        'icon_url' => null,
        'brand_color' => null,
        'reason' => null,
    ]];

    $llmMessages = [];
    iterator_to_array(
        $runner->applyPendingCalls($context, $llmMessages, $pendingCalls, []),
        preserve_keys: false,
    );

    expect($dispatcher->calls)->toHaveCount(1)
        ->and($llmMessages[0]['content'])->toBe('result of list_products');
});

// --- run(): streaming, dispatch, the approval pause, and the iteration cap ---

it('returns false and streams a final answer when the model emits no tool calls', function (): void {
    $context = tlrContext(Agent::factory()->create(), Llm::factory()->create());
    $dispatcher = tlrDispatcher();

    $transformer = tlrTransformer(function (ChatContext $ctx) {
        $ctx->appendStreamedMessage('Hello, world.');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Hello, world.');
    });
    $runner = tlrRunner($transformer, $dispatcher, tlrApprovalPolicy(false));

    $llmMessages = [['role' => 'user', 'content' => 'Hi']];
    $generator = $runner->run($context, tlrProvider(), 'gpt-4', 'openai', $llmMessages, toolDefinitions: []);
    $events = iterator_to_array($generator, preserve_keys: false);
    $paused = $generator->getReturn();

    expect($paused)->toBeFalse()
        ->and($dispatcher->calls)->toBe([])
        ->and($events)->not->toBeEmpty()
        ->and($context->streamedMessage)->toBe('Hello, world.');
});

it('dispatches an ungated tool call and continues to a final answer', function (): void {
    $context = tlrContext(Agent::factory()->create(), Llm::factory()->create());
    $dispatcher = tlrDispatcher();

    $turn = 0;
    $transformer = tlrTransformer(function (ChatContext $ctx) use (&$turn) {
        if ($turn === 0) {
            // First model turn requests a tool…
            $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'get_time', 'arguments' => '{}'];
        } else {
            // …second turn answers with the tool result in hand.
            $ctx->appendStreamedMessage('It is noon.');
        }
        $turn++;
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = tlrRunner($transformer, $dispatcher, tlrApprovalPolicy(false));

    $llmMessages = [['role' => 'user', 'content' => 'time?']];
    $generator = $runner->run($context, tlrProvider(), 'gpt-4', 'openai', $llmMessages, toolDefinitions: [['type' => 'function']]);
    iterator_to_array($generator, preserve_keys: false);
    $paused = $generator->getReturn();

    expect($paused)->toBeFalse()
        ->and($dispatcher->calls)->toHaveCount(1)
        ->and($dispatcher->calls[0]['name'])->toBe('get_time')
        ->and($context->toolCallNames)->toBe(['get_time']);

    // the dispatched tool result was appended for the model's next turn
    $toolMessages = array_values(array_filter($llmMessages, fn (array $m): bool => ($m['role'] ?? null) === 'tool'));
    expect($toolMessages)->toHaveCount(1)
        ->and($toolMessages[0]['content'])->toBe('result of get_time');
});

it('pauses the whole turn and persists a pending checkpoint when a tool call needs approval', function (): void {
    $context = tlrContext(Agent::factory()->create(), Llm::factory()->create());
    $dispatcher = tlrDispatcher();

    $transformer = tlrTransformer(function (ChatContext $ctx) {
        $ctx->pendingToolCalls[] = ['id' => 'call_1', 'name' => 'delete_user', 'arguments' => '{}'];
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    // real store so we can assert the checkpoint row is actually written
    $runner = tlrRunner($transformer, $dispatcher, tlrApprovalPolicy(true), new ToolApprovalStore);

    $llmMessages = [['role' => 'user', 'content' => 'delete me']];
    $generator = $runner->run($context, tlrProvider(), 'gpt-4', 'openai', $llmMessages, toolDefinitions: [['type' => 'function']]);
    $events = iterator_to_array($generator, preserve_keys: false);
    $paused = $generator->getReturn();

    expect($paused)->toBeTrue()              // whole turn paused
        ->and($dispatcher->calls)->toBe([]); // the gated call NEVER ran

    $checkpoint = ToolApprovalCheckpoint::query()->latest('id')->first();
    expect(ToolApprovalCheckpoint::query()->count())->toBe(1)
        ->and($checkpoint->status)->toBe(ToolApprovalCheckpoint::STATUS_PENDING)
        ->and($checkpoint->pending_calls)->toHaveCount(1)
        ->and($checkpoint->pending_calls[0]['name'])->toBe('delete_user');

    // the stream terminates with an awaiting_approval `done`
    $last = $events[array_key_last($events)];
    expect($last->data['reason'])->toBe('awaiting_approval');
});

it('stops at the configured max_iterations cap instead of looping unbounded', function (): void {
    config(['agent.tools.max_iterations' => 2]);

    $context = tlrContext(Agent::factory()->create(), Llm::factory()->create());
    $dispatcher = tlrDispatcher();

    // Every model turn keeps requesting another ungated tool — only the cap stops it.
    $counter = 0;
    $transformer = tlrTransformer(function (ChatContext $ctx) use (&$counter) {
        $ctx->pendingToolCalls[] = ['id' => 'call_'.$counter++, 'name' => 'loop_tool', 'arguments' => '{}'];
        yield StreamEvent::delta($ctx->assistantMessageId, 'x');
    });
    $runner = tlrRunner($transformer, $dispatcher, tlrApprovalPolicy(false));

    $llmMessages = [['role' => 'user', 'content' => 'go']];
    $generator = $runner->run($context, tlrProvider(), 'gpt-4', 'openai', $llmMessages, toolDefinitions: [['type' => 'function']]);
    iterator_to_array($generator, preserve_keys: false);
    $paused = $generator->getReturn();

    expect($paused)->toBeFalse()
        ->and($dispatcher->calls)->toHaveCount(2); // exactly max_iterations dispatches, then stop
});

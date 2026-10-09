<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Evaluation\Services\EvalAgentRunner;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Spectra\Facades\Spectra;

/**
 * The whole point of the eval runner is to exercise the agent's REAL pipeline
 * WITHOUT the side effects of a real chat: the runner persists no chat message
 * and charges no credit itself (it never calls finishTurn), and it switches
 * guardrails off for its own request through the DTO — never by mutating
 * global config that other code in the same worker reads.
 *
 * We fake the heavy collaborators (a real run would need a live LLM + embeddings)
 * and drive the orchestration body.
 */
beforeEach(function (): void {
    Spectra::fake();
});

/**
 * ChatService is a readonly class, so Mockery can't extend it — use a readonly
 * anonymous subclass double that builds a ChatContext from the request and
 * leaves RAG a no-op (the real method would hit embeddings).
 */
function fakeChatService(?Closure $onInit = null): ChatService
{
    return new readonly class($onInit) extends ChatService
    {
        public function __construct(private ?Closure $onInit = null) {}

        public function initializeChatContext(StreamRequestData $data): ChatContext
        {
            if ($this->onInit !== null) {
                ($this->onInit)($data);
            }

            $payload = UserTurnData::from([
                'external_session_id' => $data->externalSessionId,
                'external_conversation_id' => $data->conversationId,
                'external_message_id' => 'eval-msg',
                'model' => $data->message->model->internal_name,
                'message' => $data->message->message,
                'sent_at' => now(),
            ]);

            return new ChatContext(
                requestData: $data,
                payload: $payload,
                llmMessages: [],
                userMessageId: null,
                assistantMessageId: 'assistant-eval-1',
            );
        }

        public function enrichContextWithKnowledge(ChatContext $context): ChatContext
        {
            return $context;
        }

        public function prepareLlmMessages(ChatContext $context): array
        {
            return [];
        }
    };
}

function fakeEvalRunner(Closure $onRun, ?Closure $onInit = null): EvalAgentRunner
{
    $chatService = fakeChatService($onInit);

    $toolSelector = Mockery::mock(ToolSelector::class);
    $toolSelector->shouldReceive('selectForTurn')->andReturn([]);

    $runner = Mockery::mock(ToolLoopRunner::class);
    $runner->shouldReceive('run')->andReturnUsing(function (ChatContext $context) use ($onRun) {
        $onRun($context);

        return (function () {
            yield from [];
        })();
    });

    return new EvalAgentRunner($chatService, $runner, $toolSelector, app(LlmProviderInterface::class));
}

it('captures the answer without persisting a chat message or charging a credit', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    $messagesBefore = DB::table('chat_messages')->count();

    $runner = fakeEvalRunner(function (ChatContext $context): void {
        $context->streamedMessage = 'Eval answer.';
        $context->toolCallNames = ['lookup', 'lookup'];
    });

    $result = $runner->run($agent, 'What is your refund policy?');

    expect($result->failed())->toBeFalse()
        ->and($result->answer)->toBe('Eval answer.')
        ->and($result->toolsCalled)->toBe(['lookup'])
        ->and($result->traceId)->toBe('assistant-eval-1');

    // No chat message persisted, no credit charged (finishTurn never runs).
    expect(DB::table('chat_messages')->count())->toBe($messagesBefore);
});

it('turns guardrails off for its own request without touching global config', function (): void {
    config([
        'chatvia.agent.tools.approval_enabled' => true,
        'chatvia.governance.audit_enabled' => true,
        'chatvia.guardrails.enabled' => true,
    ]);

    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    $configDuring = null;
    $request = null;

    $runner = fakeEvalRunner(
        onRun: function (ChatContext $context) use (&$configDuring): void {
            // Anything else running in this worker during the eval must still
            // see the real settings.
            $configDuring = [
                config('chatvia.agent.tools.approval_enabled'),
                config('chatvia.governance.audit_enabled'),
                config('chatvia.guardrails.enabled'),
            ];
            $context->streamedMessage = 'ok';
        },
        onInit: function (StreamRequestData $data) use (&$request): void {
            $request = $data;
        },
    );

    $runner->run($agent, 'hello');

    expect($request->guardrailsEnabled)->toBeFalse()
        ->and($configDuring)->toBe([true, true, true])
        ->and(config('chatvia.guardrails.enabled'))->toBeTrue();
});

it('reports a pipeline failure and records it as a failed run', function (): void {
    Exceptions::fake();
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    $runner = fakeEvalRunner(function (): void {
        throw new RuntimeException('provider exploded');
    });

    $result = $runner->run($agent, 'hello');

    expect($result->failed())->toBeTrue()
        ->and($result->error)->toBe('provider exploded');
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'provider exploded');
});

it('builds the headless request with VALID uuid session + conversation ids', function (): void {
    // Regression guard: chat_conversations.external_id / sessions.external_id are
    // Postgres `uuid` columns. A prefixed id (e.g. "eval_<uuid>") throws SQLSTATE
    // 22P02 on the lookup and fails every eval run.
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    $captured = null;
    $runner = fakeEvalRunner(
        onRun: fn (ChatContext $context): string => $context->streamedMessage = 'ok',
        onInit: function (StreamRequestData $data) use (&$captured): void {
            $captured = $data;
        },
    );

    $runner->run($agent, 'hello');

    $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    expect($captured->conversationId)->toMatch($uuid)
        ->and($captured->externalSessionId)->toMatch($uuid)
        ->and($captured->source)->toBe('eval');
});

it('runs the tool loop in capture-only mode and returns the intended calls without executing them', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    $captureFlag = null;

    $toolSelector = Mockery::mock(ToolSelector::class);
    $toolSelector->shouldReceive('selectForTurn')->andReturn([['type' => 'function']]);

    $runner = Mockery::mock(ToolLoopRunner::class);
    $runner->shouldReceive('run')->andReturnUsing(
        function ($context, $provider, $modelName, $providerSlug, $llmMessages, $toolDefinitions, $extraSettings = [], $startIteration = 0, $captureToolCallsOnly = false) use (&$captureFlag) {
            $captureFlag = $captureToolCallsOnly;
            // What the runner records in capture mode: the intended call + its name.
            $context->capturedToolCalls[] = ['name' => 'send_email', 'arguments' => '{"to":"a@b.com"}'];
            $context->toolCallNames[] = 'send_email';

            return (function () {
                yield from [];
            })();
        },
    );

    $evalRunner = new EvalAgentRunner(fakeChatService(), $runner, $toolSelector, app(LlmProviderInterface::class));
    $result = $evalRunner->run($agent, 'Please email support.');

    expect($captureFlag)->toBeTrue()
        ->and($result->toolCalls)->toBe([['name' => 'send_email', 'arguments' => '{"to":"a@b.com"}']])
        ->and($result->toolsCalled)->toBe(['send_email']);
});

it('returns an error result when the agent has no model', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    // Pre-load the relations as null so loadMissing() is a no-op and llm stays null.
    $agent->setRelation('agentPrompt', null);
    $agent->setRelation('llm', null);

    $runner = fakeEvalRunner(fn (): null => null);

    $result = $runner->run($agent, 'hello');

    expect($result->failed())->toBeTrue()
        ->and($result->error)->toContain('no LLM');
});

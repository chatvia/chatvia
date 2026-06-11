<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Evaluation\Services\EvalAgentRunner;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\DB;
use Spectra\Facades\Spectra;

/**
 * The whole point of the eval runner is to exercise the agent's REAL pipeline
 * WITHOUT the side effects of a real chat: it must persist no chat message and
 * charge no message credit (it never calls finishTurn), and it must isolate the
 * headless run by disabling approval + audit and restoring them afterwards so a
 * reused queue worker is never left mutated.
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

            $payload = ChatInputPayload::from([
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

it('disables approval + audit during the run and restores them afterwards', function (): void {
    config(['chatvia.agent.tools.approval_enabled' => true]);
    config(['chatvia.governance.audit_enabled' => true]);

    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    $approvalDuring = null;
    $auditDuring = null;

    $runner = fakeEvalRunner(function (ChatContext $context) use (&$approvalDuring, &$auditDuring): void {
        $approvalDuring = config('chatvia.agent.tools.approval_enabled');
        $auditDuring = config('chatvia.governance.audit_enabled');
        $context->streamedMessage = 'ok';
    });

    $runner->run($agent, 'hello');

    expect($approvalDuring)->toBeFalse()
        ->and($auditDuring)->toBeFalse()
        ->and(config('chatvia.agent.tools.approval_enabled'))->toBeTrue()
        ->and(config('chatvia.governance.audit_enabled'))->toBeTrue();
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

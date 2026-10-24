<?php

use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\ScoreEvalCaseAction;
use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Events\EvalCaseScored;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Domain\Evaluation\Services\EvalAgentRunner;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\Evaluation\EvalFixtures;

/**
 * ScoreEvalCase glues the runner to the scorer and writes the immutable result.
 * We fake the runner (the real one needs a live LLM) and assert the mapping:
 * the captured answer/sources/tools/trace land on the row, the scorer's verdict
 * drives pass/score, and a failed agent run becomes a failing result — never an
 * exception that would abort the batch. It also counts one message against the
 * workspace's monthly limit per case that produced an answer, and checks the
 * same limit as live chat before spending an LLM call.
 */
beforeEach(function (): void {
    Bus::fake();
});

/**
 * A running eval run of a new agent with a model, and one active case of it
 * scored by the given scorer.
 *
 * @param  array<string, mixed>  $config
 * @return array{0: EvalRun, 1: EvalCase}
 */
function secaFixtures(ScorerType $scorer = ScorerType::Contains, array $config = ['value' => 'paris']): array
{
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    return [
        EvalRun::factory()->forAgent($agent)->create(['total_cases' => 1]),
        EvalCase::factory()->forAgent($agent)->create(['input' => 'Capital of France?', 'scorer_type' => $scorer, 'scorer_config' => $config]),
    ];
}

/** The runner, answering every case with this captured run. */
function secaRunnerReturns(AgentRunResultData $data): void
{
    $runner = Mockery::mock(EvalAgentRunner::class);
    $runner->shouldReceive('run')->andReturn($data);

    app()->instance(EvalAgentRunner::class, $runner);
}

it('records a passing result with the captured run state', function (): void {
    [$run, $case] = secaFixtures();
    secaRunnerReturns(EvalFixtures::run('The capital is Paris.', [['name' => 'geo_lookup', 'arguments' => '{}']], sources: ['World Atlas'], tokens: 42, traceId: 'trace-abc'));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->has_passed)->toBeTrue()
        ->and($result->score)->toBe(1.0)
        ->and($result->actual_output)->toBe('The capital is Paris.')
        ->and($result->sources_used)->toBe(['World Atlas'])
        ->and($result->tools_called)->toBe(['geo_lookup'])
        ->and($result->tokens)->toBe(42)
        ->and($result->trace_id)->toBe('trace-abc')
        ->and($result->run_id)->toBe($run->id);
});

it('scores a tool_call case from the captured (never executed) tool call', function (): void {
    [$run, $case] = secaFixtures(ScorerType::ToolCall, ['tool' => 'send_email', 'arguments' => '{"to": "support@acme.com"}']);

    // The runner captures the intended call and never executes it: the result
    // carries no text answer, only the tool call.
    secaRunnerReturns(EvalFixtures::run(toolCalls: [['name' => 'send_email', 'arguments' => '{"to": "support@acme.com"}']]));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->has_passed)->toBeTrue()
        ->and($result->scorer_type)->toBe(ScorerType::ToolCall)
        ->and($result->tools_called)->toBe(['send_email']);
});

it('records a failing result when the scorer rejects the answer', function (): void {
    [$run, $case] = secaFixtures(config: ['value' => 'berlin']);
    secaRunnerReturns(EvalFixtures::run('The capital is Paris.'));

    expect(app(ScoreEvalCaseAction::class)->execute($run, $case)->has_passed)->toBeFalse();
});

it('records a failing result (not an exception) when the agent run errored', function (): void {
    [$run, $case] = secaFixtures();
    secaRunnerReturns(EvalFixtures::run(error: 'model timeout', tokens: null));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->has_passed)->toBeFalse()
        ->and($result->judge_rationale)->toContain('Agent run failed')
        ->and($result->judge_rationale)->toContain('model timeout');
});

it('counts one message against the workspace limit only for a case that produced an answer', function (AgentRunResultData $outcome, int $counted): void {
    [$run, $case] = secaFixtures();
    app(SetMessageLimitAction::class)->execute($run->agent->workspace, 10);
    secaRunnerReturns($outcome);

    app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect(MessageLimit::query()->where('workspace_id', $run->agent->workspace_id)->value('messages_used'))->toBe($counted);
})->with([
    'an answer' => [fn (): AgentRunResultData => EvalFixtures::run('The capital is Paris.'), 1],
    'a failed run' => [fn (): AgentRunResultData => EvalFixtures::run(error: 'model timeout', tokens: null), 0],
]);

it('skips the LLM call when the workspace has reached its monthly message limit', function (): void {
    [$run, $case] = secaFixtures();
    MessageLimit::factory()->forWorkspace($run->agent->workspace)->reached()->create();

    // The runner must never be invoked — a case over the limit spends no LLM call.
    $runner = Mockery::mock(EvalAgentRunner::class);
    $runner->shouldNotReceive('run');
    app()->instance(EvalAgentRunner::class, $runner);

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->has_passed)->toBeFalse()
        ->and($result->judge_rationale)->toContain('Message limit reached');
});

it('broadcasts the scored case with the run external id and running pass/fail tally', function (): void {
    [$run, $case] = secaFixtures();
    $agent = $run->agent;

    // Partial fake so HasExternalId's model `creating` hook still assigns the
    // (NOT NULL) external_id to the result row written by the action.
    Event::fake([EvalCaseScored::class]);
    secaRunnerReturns(EvalFixtures::run('The capital is Paris.'));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    Event::assertDispatched(
        EvalCaseScored::class,
        // broadcastWith() in the closure exercises the result resource serialization.
        function (EvalCaseScored $e) use ($run, $agent, $result): bool {
            $payload = $e->broadcastWith();

            return $e->result->is($result)
                && $e->agentExternalId === $agent->external_id
                && $payload['run_external_id'] === $run->external_id
                && $payload['result']['id'] === $result->id
                && $payload['passed_count'] === 1
                && $payload['failed_count'] === 0;
        },
    );
});

it('caps a multibyte answer on a character boundary so the result row is stored', function (string $answer): void {
    // 9 bytes splits a 2-byte Arabic letter / 4-byte emoji; a byte substr()
    // would leave invalid UTF-8 and Postgres would reject the insert.
    config(['chatvia.evals.max_output_bytes' => 9]);
    [$run, $case] = secaFixtures();
    secaRunnerReturns(EvalFixtures::run($answer));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case)->fresh();

    expect(mb_check_encoding((string) $result->actual_output, 'UTF-8'))->toBeTrue()
        ->and(strlen((string) $result->actual_output))->toBeLessThan(9)
        ->and(Str::startsWith($answer, (string) $result->actual_output))->toBeTrue()
        ->and($result->actual_output_truncated)->toBeTrue()
        ->and($result->actual_output_sha256)->toBe(hash('sha256', $answer));
})->with([
    'arabic' => 'العاصمة هي باريس',
    'emoji' => 'Paris 🇫🇷🇫🇷 yes',
]);

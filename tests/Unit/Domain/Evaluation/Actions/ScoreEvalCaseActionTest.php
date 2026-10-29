<?php

use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\ScoreEvalCaseAction;
use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Domain\Evaluation\Services\EvalAgentRunner;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\Support\Evaluation\EvalFixtures;

/**
 * ScoreEvalCase glues the runner to the scorer and writes the immutable result.
 * We fake the runner (the real one needs a live LLM) and assert the mapping:
 * the captured answer/sources/tools/trace land on the row (the answer capped on
 * a character boundary), the scorer's verdict drives pass/score, and a failed
 * agent run becomes a failing result — never an exception that would abort
 * the batch. It also counts one message against the workspace's monthly limit
 * per case that produced an answer, and checks the same limit as live chat
 * before spending an LLM call.
 */
beforeEach(function (): void {
    Bus::fake();
});

/**
 * A running eval run of a new agent with a model, and one active case of it
 * scored by a "contains paris" check.
 *
 * @return array{0: EvalRun, 1: EvalCase}
 */
function secaFixtures(): array
{
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    return [
        EvalRun::factory()->forAgent($agent)->create(['total_cases' => 1]),
        EvalCase::factory()->forAgent($agent)->create(['input' => 'Capital of France?', 'scorer_type' => ScorerType::Contains, 'scorer_config' => ['value' => 'paris']]),
    ];
}

/** The runner, answering every case with this captured run. */
function secaRunnerReturns(AgentRunResultData $data): void
{
    $runner = Mockery::mock(EvalAgentRunner::class);
    $runner->shouldReceive('run')->andReturn($data);

    app()->instance(EvalAgentRunner::class, $runner);
}

it('records a passing result with the captured run state and counts one message', function (): void {
    config(['chatvia.evals.max_output_bytes' => 9]);
    [$run, $case] = secaFixtures();
    app(SetMessageLimitAction::class)->execute($run->agent->workspace, 10);
    $answer = 'Paris 🇫🇷🇫🇷 yes';
    secaRunnerReturns(EvalFixtures::run($answer, [['name' => 'geo_lookup', 'arguments' => '{}']], sources: ['World Atlas'], tokens: 42, traceId: 'trace-abc'));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case)->fresh();

    expect($result->has_passed)->toBeTrue()
        ->and($result->score)->toBe(1.0)
        ->and(mb_check_encoding((string) $result->actual_output, 'UTF-8'))->toBeTrue()
        ->and(strlen((string) $result->actual_output))->toBeLessThan(9)
        ->and(Str::startsWith($answer, (string) $result->actual_output))->toBeTrue()
        ->and($result->actual_output_truncated)->toBeTrue()
        ->and($result->actual_output_sha256)->toBe(hash('sha256', $answer))
        ->and($result->sources_used)->toBe(['World Atlas'])
        ->and($result->tools_called)->toBe(['geo_lookup'])
        ->and($result->tokens)->toBe(42)
        ->and($result->trace_id)->toBe('trace-abc')
        ->and($result->run_id)->toBe($run->id)
        ->and(MessageLimit::query()->where('workspace_id', $run->agent->workspace_id)->value('messages_used'))->toBe(1);
});

it('records a failing result, not an exception, for an errored run and counts no message', function (): void {
    [$run, $case] = secaFixtures();
    app(SetMessageLimitAction::class)->execute($run->agent->workspace, 10);
    secaRunnerReturns(EvalFixtures::run(error: 'model timeout', tokens: null));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->has_passed)->toBeFalse()
        ->and($result->judge_rationale)->toContain('Agent run failed')
        ->and($result->judge_rationale)->toContain('model timeout')
        ->and(MessageLimit::query()->where('workspace_id', $run->agent->workspace_id)->value('messages_used'))->toBe(0);
});

it('skips the LLM call when the workspace has reached its monthly message limit', function (): void {
    [$run, $case] = secaFixtures();
    MessageLimit::factory()->forWorkspace($run->agent->workspace)->reached()->create();
    $runner = Mockery::mock(EvalAgentRunner::class);
    $runner->shouldNotReceive('run');
    app()->instance(EvalAgentRunner::class, $runner);

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->has_passed)->toBeFalse()
        ->and($result->judge_rationale)->toContain('Message limit reached');
});

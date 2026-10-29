<?php

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\FinalizeEvalRunAction;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Models\EvalResult;
use Domain\Evaluation\Models\EvalRun;

/**
 * Finalize is the single place a run's headline numbers are computed, straight
 * from the immutable result rows — so a partial/cancelled batch still leaves
 * coherent counts. A regression here would mislead every "is the agent getting
 * better?" decision.
 */
function ferRun(Agent $agent, int $total = 0): EvalRun
{
    return EvalRun::factory()->forAgent($agent)->create(['total_cases' => $total]);
}

function ferResult(EvalRun $run, bool $pass, ?float $score, ?int $tokens): EvalResult
{
    return EvalResult::create([
        'run_id' => $run->id,
        'case_id' => null,
        'case_name' => 'case',
        'scorer_type' => 'contains',
        'has_passed' => $pass,
        'score' => $score,
        'tokens' => $tokens,
    ]);
}

it('counts this run\'s results, averages only the scores there are, and completes the run', function (): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();
    $run = ferRun($agent, 4);
    $other = ferRun($agent, 1);
    ferResult($run, true, 0.3333, 12);
    ferResult($run, false, 0.1667, 7);
    ferResult($run, false, null, null);
    ferResult($run, true, 0.9999, 1);
    ferResult($other, true, 1.0, 500);

    $finalized = app(FinalizeEvalRunAction::class)->execute($run->fresh());

    expect($finalized->status)->toBe(EvalRunStatus::Completed)
        ->and($finalized->passed_count)->toBe(2)
        ->and($finalized->failed_count)->toBe(2)
        ->and((float) $finalized->avg_score)->toBe(0.5)
        ->and((int) $finalized->total_tokens)->toBe(20)
        ->and($finalized->finished_at)->toEqual(now());
});

it('marks the run failed when the batch was cancelled', function (): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();
    $run = ferRun($agent);

    $finalized = app(FinalizeEvalRunAction::class)->execute($run->fresh(), EvalRunStatus::Failed);

    expect($finalized->status)->toBe(EvalRunStatus::Failed)
        ->and($finalized->avg_score)->toBeNull()
        ->and($finalized->finished_at)->toEqual(now());
});

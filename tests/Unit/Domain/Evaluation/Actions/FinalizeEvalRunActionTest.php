<?php

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\FinalizeEvalRunAction;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Events\EvalRunProgressed;
use Domain\Evaluation\Models\EvalResult;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Support\Facades\Event;

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

it('aggregates results and marks the run completed', function (): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();
    $run = ferRun($agent, 3);

    ferResult($run, true, 1.0, 100);
    ferResult($run, true, 0.8, 50);
    ferResult($run, false, 0.0, 30);

    $finalized = (new FinalizeEvalRunAction)->execute($run->fresh());

    expect($finalized->status)->toBe(EvalRunStatus::Completed)
        ->and($finalized->passed_count)->toBe(2)
        ->and($finalized->failed_count)->toBe(1)
        ->and((float) $finalized->avg_score)->toBe(0.6)
        ->and((int) $finalized->total_tokens)->toBe(180)
        ->and($finalized->finished_at)->toEqual(now());
});

it('averages and sums only the scores and tokens there are, of this run only', function (): void {
    $agent = Agent::factory()->create();
    $run = ferRun($agent, 4);
    $other = ferRun($agent, 1);

    ferResult($run, true, 0.3333, 12);
    ferResult($run, false, 0.1667, 7);
    ferResult($run, false, null, null);
    ferResult($run, true, 0.9999, 1);
    ferResult($other, true, 1.0, 500);

    $finalized = (new FinalizeEvalRunAction)->execute($run->fresh());

    expect($finalized->passed_count)->toBe(2)
        ->and($finalized->failed_count)->toBe(2)
        // (0.3333 + 0.1667 + 0.9999) / 3, to four places; 12 + 7 + 1 tokens.
        ->and((float) $finalized->avg_score)->toBe(0.5)
        ->and((int) $finalized->total_tokens)->toBe(20);
});

it('marks the run failed when the batch was cancelled', function (): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();
    $run = ferRun($agent);

    $finalized = (new FinalizeEvalRunAction)->execute($run->fresh(), cancelled: true);

    expect($finalized->status)->toBe(EvalRunStatus::Failed)
        ->and($finalized->avg_score)->toBeNull()
        ->and($finalized->finished_at)->toEqual(now());
});

it('broadcasts the finalized run so open Evals pages update live', function (): void {
    Event::fake([EvalRunProgressed::class]);

    $agent = Agent::factory()->create();
    $run = ferRun($agent, 1);
    ferResult($run, true, 1.0, 10);

    (new FinalizeEvalRunAction)->execute($run->fresh());

    Event::assertDispatched(
        EvalRunProgressed::class,
        // broadcastWith() in the closure exercises the resource serialization.
        function (EvalRunProgressed $e) use ($run, $agent): bool {
            $payload = $e->broadcastWith();

            return $e->agentExternalId === $agent->external_id
                && $payload['run']['external_id'] === $run->external_id
                && $payload['run']['status'] === EvalRunStatus::Completed->value
                && $payload['run']['passed_count'] === 1;
        },
    );
});

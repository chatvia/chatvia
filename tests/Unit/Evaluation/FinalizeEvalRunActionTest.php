<?php

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\FinalizeEvalRunAction;
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
function runningRun(Agent $agent, int $total = 0): EvalRun
{
    return EvalRun::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->user_id,
        'status' => EvalRun::STATUS_RUNNING,
        'total_cases' => $total,
        'started_at' => now(),
    ]);
}

function resultRow(EvalRun $run, bool $pass, float $score, int $tokens): EvalResult
{
    return EvalResult::create([
        'run_id' => $run->id,
        'case_id' => null,
        'case_name' => 'case',
        'scorer_type' => 'contains',
        'pass' => $pass,
        'score' => $score,
        'tokens' => $tokens,
    ]);
}

it('aggregates results and marks the run completed', function (): void {
    $agent = Agent::factory()->create();
    $run = runningRun($agent, 3);

    resultRow($run, true, 1.0, 100);
    resultRow($run, true, 0.8, 50);
    resultRow($run, false, 0.0, 30);

    $finalized = (new FinalizeEvalRunAction)->execute($run->fresh());

    expect($finalized->status)->toBe(EvalRun::STATUS_COMPLETED)
        ->and($finalized->passed_count)->toBe(2)
        ->and($finalized->failed_count)->toBe(1)
        ->and((float) $finalized->avg_score)->toBe(0.6)
        ->and((int) $finalized->total_tokens)->toBe(180)
        ->and($finalized->finished_at)->not->toBeNull();
});

it('marks the run failed when the batch was cancelled', function (): void {
    $agent = Agent::factory()->create();
    $run = runningRun($agent);

    $finalized = (new FinalizeEvalRunAction)->execute($run->fresh(), cancelled: true);

    expect($finalized->status)->toBe(EvalRun::STATUS_FAILED)
        ->and($finalized->avg_score)->toBeNull()
        ->and($finalized->finished_at)->not->toBeNull();
});

it('broadcasts the finalized run so open Evals pages update live', function (): void {
    Event::fake([EvalRunProgressed::class]);

    $agent = Agent::factory()->create();
    $run = runningRun($agent, 1);
    resultRow($run, true, 1.0, 10);

    (new FinalizeEvalRunAction)->execute($run->fresh());

    Event::assertDispatched(
        EvalRunProgressed::class,
        // broadcastWith() in the closure exercises the resource serialization.
        function (EvalRunProgressed $e) use ($run, $agent): bool {
            $payload = $e->broadcastWith();

            return $e->agentExternalId === $agent->external_id
                && $payload['run']['external_id'] === $run->external_id
                && $payload['run']['status'] === EvalRun::STATUS_COMPLETED
                && $payload['run']['passed_count'] === 1;
        },
    );
});

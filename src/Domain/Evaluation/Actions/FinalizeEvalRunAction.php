<?php

declare(strict_types=1);

namespace Domain\Evaluation\Actions;

use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Events\EvalRunFinished;
use Domain\Evaluation\Events\EvalRunProgressed;
use Domain\Evaluation\Models\EvalResult;
use Domain\Evaluation\Models\EvalRun;

/**
 * Closes out an eval run by aggregating its recorded results — the immutable
 * {@see EvalResult} rows are the source of truth, so
 * the run's counts are always consistent even after a partial/cancelled batch.
 * The totals are summed in SQL: a result row carries the agent's whole answer,
 * which the close-out does not need. Invoked from the Bus::batch `finally()`
 * callback (and inline when a run has no active cases). The person who
 * started the run is told it finished ({@see EvalRunFinished}).
 */
class FinalizeEvalRunAction
{
    public function execute(EvalRun $run, bool $cancelled = false): EvalRun
    {
        // avg and sum skip NULL scores and tokens, as the old in-memory sums did.
        $totals = $run->results()->toBase()
            ->selectRaw('count(*) AS total, count(*) FILTER (WHERE has_passed) AS passed, avg(score) AS avg_score, sum(tokens) AS total_tokens')
            ->first();

        $total = (int) $totals->total;
        $passed = (int) $totals->passed;

        $run->update([
            'status' => $cancelled ? EvalRunStatus::Failed : EvalRunStatus::Completed,
            'passed_count' => $passed,
            'failed_count' => $total - $passed,
            'avg_score' => $total === 0 ? null : round((float) $totals->avg_score, 4),
            'total_tokens' => (int) $totals->total_tokens,
            'finished_at' => now(),
        ]);

        $agentExternalId = $run->agent?->external_id;

        if ($agentExternalId !== null) {
            // Carry the actor's avatar on the close-out broadcast too (eager-load
            // so the resource never lazy-loads under Model::shouldBeStrict()).
            $run->loadMissing('triggeredByUser');
            EvalRunProgressed::dispatch($run, $agentExternalId);
            EvalRunFinished::dispatch($run);
        }

        return $run;
    }
}

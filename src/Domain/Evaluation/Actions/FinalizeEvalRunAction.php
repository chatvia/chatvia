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
 * Invoked from the Bus::batch `finally()` callback (and inline when a run has no
 * active cases). The person who started the run is told it finished
 * ({@see EvalRunFinished}).
 */
class FinalizeEvalRunAction
{
    public function execute(EvalRun $run, bool $cancelled = false): EvalRun
    {
        $results = $run->results()->get();

        $passed = $results->where('has_passed', true)->count();
        $failed = $results->count() - $passed;

        $run->update([
            'status' => $cancelled ? EvalRunStatus::Failed : EvalRunStatus::Completed,
            'passed_count' => $passed,
            'failed_count' => $failed,
            'avg_score' => $results->isEmpty() ? null : round((float) $results->avg('score'), 4),
            'total_tokens' => (int) $results->sum('tokens'),
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

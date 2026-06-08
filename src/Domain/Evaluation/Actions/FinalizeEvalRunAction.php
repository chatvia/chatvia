<?php

namespace Domain\Evaluation\Actions;

use Domain\Evaluation\Models\EvalResult;
use Domain\Evaluation\Models\EvalRun;

/**
 * Closes out an eval run by aggregating its recorded results — the immutable
 * {@see EvalResult} rows are the source of truth, so
 * the run's counts are always consistent even after a partial/cancelled batch.
 * Invoked from the Bus::batch `finally()` callback (and inline when a run has no
 * active cases).
 */
class FinalizeEvalRunAction
{
    public function execute(EvalRun $run, bool $cancelled = false): EvalRun
    {
        $results = $run->results()->get();

        $passed = $results->where('pass', true)->count();
        $failed = $results->count() - $passed;

        $run->update([
            'status' => $cancelled ? EvalRun::STATUS_FAILED : EvalRun::STATUS_COMPLETED,
            'passed_count' => $passed,
            'failed_count' => $failed,
            'avg_score' => $results->isEmpty() ? null : round((float) $results->avg('score'), 4),
            'total_tokens' => (int) $results->sum('tokens'),
            'finished_at' => now(),
        ]);

        return $run;
    }
}

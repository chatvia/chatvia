<?php

namespace Domain\Evaluation\Jobs;

use Domain\Evaluation\Actions\ScoreEvalCaseAction;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;

/**
 * One queued case score within an eval-run batch. Holds no logic — defers to
 * {@see ScoreEvalCaseAction}. Bails early if the batch was cancelled so a
 * cancelled run stops consuming worker time.
 */
// The action records a failed run as a failing result, so a retry would only repeat its model calls.
#[Tries(1)]
// A run or case deleted before the job runs needs no score.
#[DeleteWhenMissingModels]
class ScoreEvalCaseJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(
        public readonly EvalRun $run,
        public readonly EvalCase $case,
    ) {}

    public function handle(ScoreEvalCaseAction $action): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $action->execute($this->run, $this->case);
    }
}

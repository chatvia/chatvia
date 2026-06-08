<?php

namespace Domain\Evaluation\Jobs;

use Domain\Evaluation\Actions\ScoreEvalCaseAction;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One queued case score within an eval-run batch. Holds no logic — defers to
 * {@see ScoreEvalCaseAction}. Bails early if the batch was cancelled so a
 * cancelled run stops consuming worker time.
 */
class ScoreEvalCaseJob implements ShouldQueue
{
    use Batchable, Queueable;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 1;

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

<?php

declare(strict_types=1);

namespace Domain\Evaluation\Events;

use Domain\Evaluation\Models\EvalRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An eval run finished (completed, or failed when its batch was cancelled)
 * and its counts are final, so the person who started it can be told.
 */
class EvalRunFinished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public EvalRun $run,
    ) {}
}

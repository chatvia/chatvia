<?php

declare(strict_types=1);

namespace Domain\Evaluation\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where an eval run is in its life: Running while its cases score on the
 * queue, then Completed — or Failed when the batch was cancelled. Stored in
 * `eval_runs.status` (a database enum), so the values must not change.
 */
#[TypeScript]
enum EvalRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}

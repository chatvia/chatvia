<?php

namespace Domain\Evaluation\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Support\Concerns\HasExternalId;

/**
 * Immutable per-case result of an eval run — the agent's actual answer (redacted
 * + size-capped), the pass/score, judge rationale, and the RAG sources + tools
 * the agent used. Insert-only (no updated_at), mirroring AgentActivity.
 *
 * @property string $external_id
 * @property bool $pass
 * @property float|null $score
 */
#[Guarded(['id'])]
class EvalResult extends Model
{
    use HasExternalId;

    /** Insert-only. */
    public const UPDATED_AT = null;

    public function run(): BelongsTo
    {
        return $this->belongsTo(EvalRun::class, 'run_id');
    }

    protected function casts(): array
    {
        return [
            'pass' => 'boolean',
            'score' => 'float',
            'sources_used' => 'array',
            'tools_called' => 'array',
            'actual_output_truncated' => 'boolean',
        ];
    }
}

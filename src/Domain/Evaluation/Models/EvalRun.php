<?php

namespace Domain\Evaluation\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * One execution of an agent's whole eval suite. Mutable while it runs (status +
 * counts transition), finalized by the Bus::batch then() callback.
 *
 * @property string $external_id
 * @property string $status
 * @property int|null $agent_id
 */
class EvalRun extends Model
{
    use HasExternalId;

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'eval_runs';

    protected $guarded = ['id'];

    protected $casts = [
        'avg_score' => 'float',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(EvalResult::class, 'run_id');
    }

    public function scopeForAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }
}

<?php

namespace Domain\Evaluation\Models;

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Enums\ScorerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Support\Concerns\HasExternalId;

/**
 * A single agent test case: an input prompt + how its answer is scored. Edited
 * by teammates with `agent.evals.manage`; `agent_name` is denormalized so the
 * case stays readable after the agent is deleted.
 *
 * @property string $external_id
 * @property int|null $agent_id
 * @property ScorerType $scorer_type
 * @property array<string, mixed>|null $scorer_config
 */
class EvalCase extends Model
{
    use HasExternalId;

    protected $table = 'eval_cases';

    protected $guarded = ['id'];

    protected $casts = [
        'scorer_type' => ScorerType::class,
        'scorer_config' => 'array',
        'active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function scopeForAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}

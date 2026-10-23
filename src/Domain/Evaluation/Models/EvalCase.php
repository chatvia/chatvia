<?php

namespace Domain\Evaluation\Models;

use Database\Factories\EvalCaseFactory;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Enums\ScorerType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property bool $is_active
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class EvalCase extends Model
{
    /** @use HasFactory<EvalCaseFactory> */
    use HasExternalId, HasFactory;

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function forAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function forWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'scorer_type' => ScorerType::class,
            'scorer_config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

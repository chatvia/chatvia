<?php

namespace Domain\Evaluation\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Enums\EvalRunStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;

/**
 * One execution of an agent's whole eval suite. Mutable while it runs (status +
 * counts transition), finalized by the Bus::batch finally() callback, so a run
 * with failed cases is still closed.
 *
 * @property string $external_id
 * @property EvalRunStatus $status
 * @property int|null $agent_id
 * @property float|null $avg_score
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class EvalRun extends Model
{
    use HasExternalId;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return HasMany<EvalResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(EvalResult::class, 'run_id');
    }

    /**
     * The teammate who triggered the run. Nulls on user delete; `triggered_by_name`
     * is kept denormalized so the row stays readable after the account is gone.
     *
     * @return BelongsTo<User, $this>
     */
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    #[Scope]
    protected function forAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    #[Scope]
    protected function forWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    protected function casts(): array
    {
        return [
            'status' => EvalRunStatus::class,
            'avg_score' => 'float',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}

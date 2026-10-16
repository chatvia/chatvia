<?php

namespace Domain\Guardrail\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardSeverity;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Listeners\RecordGuardrailEvent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Support\Concerns\HasExternalId;

/**
 * Immutable record of one guardrail trigger (PII / prompt-injection / blocked
 * topic / toxicity) on the live chat. A clean pass writes nothing — only blocks,
 * redactions, and flags. Written off the hot path by the queued
 * {@see RecordGuardrailEvent} listener; read by the
 * per-agent Guardrails page. Denormalized `agent_name`/`user_name` keep a row
 * readable after the agent or visitor is deleted, exactly like `agent_activities`.
 *
 * @property string $external_id
 * @property int|null $agent_id
 * @property string $agent_name
 * @property int $workspace_id
 * @property int|null $user_id
 * @property int|null $chatter_id
 * @property string|null $user_name
 * @property string|null $visitor_key
 * @property string|null $conversation_external_id
 * @property GuardStage $stage
 * @property GuardType $guard_type
 * @property GuardOutcome $outcome
 * @property GuardSeverity|null $severity
 * @property array<string, mixed>|null $detail
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class GuardrailEvent extends Model
{
    use HasExternalId;

    /** Insert-only audit log. */
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
    protected function forAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    protected function casts(): array
    {
        return [
            'stage' => GuardStage::class,
            'guard_type' => GuardType::class,
            'outcome' => GuardOutcome::class,
            'severity' => GuardSeverity::class,
            'detail' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

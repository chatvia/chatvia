<?php

namespace Domain\Conversation\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Tools\RecordToolCallAuditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable per-tool-call governance audit row (`agent_activity`) for the
 * Governed MCP Gateway — one row per tool decision (auto / approved / denied /
 * rate_limited). Written once by
 * {@see RecordToolCallAuditAction} off the
 * streaming hot path; read by the account (Settings → Governance) and per-agent
 * Audit pages.
 *
 * `agent_id`/`user_id` are nullable FKs that null on delete, with denormalized
 * `agent_name`/`user_name` fallbacks, so a row stays readable after the agent
 * or user is removed.
 *
 * @property int|null $agent_id
 * @property string $agent_name
 * @property int|null $user_id
 * @property string|null $user_name
 */
class AgentActivity extends Model
{
    /** Insert-only audit log. */
    public const UPDATED_AT = null;

    protected $table = 'agent_activity';

    protected $guarded = ['id'];

    protected $casts = [
        'arguments' => 'array',
        'gated' => 'boolean',
        'is_error' => 'boolean',
        'arguments_truncated' => 'boolean',
        'result_truncated' => 'boolean',
        'decided_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeForAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Shape one row for the audit viewers (account + per-agent). The live avatar
     * is taken from the eager-loaded `user` relation when present; rows without a
     * user (or a deleted user) fall back to the denormalized name / "visitor".
     *
     * @return array<string, mixed>
     */
    public function toAuditArray(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'outcome' => $this->outcome,
            'agent_id' => $this->agent_id,
            'agent_name' => $this->agent_name,
            'agent_external_id' => $this->relationLoaded('agent') ? $this->agent?->external_id : null,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'avatar' => $this->relationLoaded('user') ? $this->user?->avatar : null,
            'trace_id' => $this->trace_id,
            'tool_name' => $this->tool_name,
            'tool_kind' => $this->tool_kind,
            'connector_slug' => $this->connector_slug,
            'connector_name' => $this->connector_name,
            'is_error' => $this->is_error,
            'gated' => $this->gated,
            'latency_ms' => $this->latency_ms,
            'arguments' => $this->arguments,
            'result_text' => $this->result_text,
            'conversation_external_id' => $this->conversation_external_id,
            'decided_at' => $this->decided_at?->toIso8601String(),
        ];
    }
}

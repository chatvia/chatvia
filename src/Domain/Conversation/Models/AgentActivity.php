<?php

namespace Domain\Conversation\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Tools\RecordToolCallAuditAction;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable per-tool-call governance audit row (`agent_activity`) for the
 * Governed MCP Gateway — one row per tool decision (a ToolCallOutcome: auto /
 * approved / denied / rate_limited / not_offered). Written once by
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
 * @property int $workspace_id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $conversation_external_id
 * @property string $trace_id
 * @property string|null $request_id
 * @property string $tool_call_id
 * @property string $tool_name
 * @property string $tool_kind
 * @property string|null $connector_slug
 * @property string|null $connector_name
 * @property string $outcome
 * @property bool $gated
 * @property bool $is_error
 * @property int|null $latency_ms
 * @property array<string, mixed>|null $arguments
 * @property string|null $arguments_sha256
 * @property bool $arguments_truncated
 * @property string|null $result_text
 * @property bool $result_truncated
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon|null $created_at
 */
#[Guarded(['id'])]
#[Table(name: 'agent_activity')]
class AgentActivity extends Model
{
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
            'arguments' => 'array',
            'gated' => 'boolean',
            'is_error' => 'boolean',
            'arguments_truncated' => 'boolean',
            'result_truncated' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }
}

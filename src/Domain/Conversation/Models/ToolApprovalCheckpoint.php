<?php

namespace Domain\Conversation\Models;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Tooling\ApprovalContextData;
use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;

/**
 * A paused agent turn awaiting human approval of one or more tool calls.
 * Created by the tool loop when it hits a gated call (through
 * {@see ToolApprovalStore}); claimed once by the resume flow, which applies
 * the person's decisions and continues streaming.
 *
 * @property int $id
 * @property string $external_id
 * @property int $agent_id
 * @property string $conversation_external_id
 * @property string $assistant_message_id
 * @property string|null $request_id
 * @property int $iteration
 * @property ToolApprovalStatus $status
 * @property array<int, array<string, mixed>> $messages_snapshot
 * @property array<int, array<string, mixed>> $pending_calls
 * @property array<string, mixed> $context
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'tool_approval_checkpoints')]
#[RouteKey('external_id')]
class ToolApprovalCheckpoint extends Model
{
    use HasExternalId;

    protected function casts(): array
    {
        return [
            'status' => ToolApprovalStatus::class,
            'messages_snapshot' => 'array',
            'pending_calls' => 'array',
            'context' => 'array',
            'iteration' => 'int',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * A checkpoint can be resumed only while it is still pending and unexpired.
     */
    public function isResumable(): bool
    {
        if ($this->status !== ToolApprovalStatus::Pending) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Every tool call of the paused turn (gated and not), with its decision
     * and display details.
     *
     * @return array<int, PendingToolCallData>
     */
    public function pendingToolCalls(): array
    {
        return array_map(PendingToolCallData::fromArray(...), $this->pending_calls);
    }

    /** What the paused turn needs to resume. */
    public function approvalContext(): ApprovalContextData
    {
        return ApprovalContextData::fromArray($this->context);
    }
}

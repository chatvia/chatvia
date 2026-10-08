<?php

namespace Domain\Conversation\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;

/**
 * A paused agent turn awaiting human approval of one or more tool calls.
 * Created by LlmSource when the tool loop hits a gated call; consumed once by
 * the resume flow, which applies the user's decisions and continues streaming.
 *
 * @property int $id
 * @property string $external_id
 * @property int $agent_id
 * @property string $conversation_external_id
 * @property string $assistant_message_id
 * @property string|null $request_id
 * @property int $iteration
 * @property string $status
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

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_RESOLVED = 'resolved';

    public const string STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'messages_snapshot' => 'array',
            'pending_calls' => 'array',
            'context' => 'array',
            'iteration' => 'int',
            'expires_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * A checkpoint can be resumed only while it is still pending and unexpired.
     */
    public function isResumable(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}

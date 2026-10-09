<?php

namespace Domain\Conversation\Services\Tooling\Approval;

use Domain\Conversation\Data\Tooling\ApprovalContextData;
use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Illuminate\Support\Facades\DB;

/**
 * Persists and resolves the checkpoints that back human-in-the-loop tool
 * approval. A checkpoint captures everything needed to resume a paused agent
 * turn; it is claimed exactly once so a double-submit can never run the tools
 * twice.
 */
class ToolApprovalStore
{
    /** How long a paused turn waits for a decision when not configured. */
    private const int DEFAULT_TTL_MINUTES = 1440;

    /**
     * Snapshot a paused turn. `$pendingCalls` carries every tool call in the
     * turn (auto and gated) so the resume can apply per-call decisions.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, PendingToolCallData>  $pendingCalls
     */
    public function persist(ChatContext $context, array $llmMessages, array $pendingCalls, int $iteration): ToolApprovalCheckpoint
    {
        return ToolApprovalCheckpoint::create([
            'agent_id' => $context->requestData->agent->id,
            'conversation_external_id' => $context->requestData->conversationId,
            'assistant_message_id' => $context->assistantMessageId,
            'request_id' => $context->requestData->requestId,
            'iteration' => $iteration,
            'status' => ToolApprovalStatus::Pending,
            'messages_snapshot' => $llmMessages,
            'pending_calls' => array_map(static fn (PendingToolCallData $call): array => $call->toArray(), $pendingCalls),
            'context' => ApprovalContextData::fromContext($context)->toArray(),
            'expires_at' => now()->addMinutes((int) config('chatvia.agent.tools.approval_ttl_minutes', self::DEFAULT_TTL_MINUTES)),
        ]);
    }

    /**
     * Atomically claim a checkpoint for resumption: lock the row, verify it is
     * still pending and unexpired, flip it to resolved, and return it. Returns
     * null when the checkpoint is missing, already resolved, or expired — so a
     * second concurrent resolve gets nothing to run.
     */
    public function claim(string $externalId): ?ToolApprovalCheckpoint
    {
        return DB::transaction(function () use ($externalId): ?ToolApprovalCheckpoint {
            $checkpoint = ToolApprovalCheckpoint::query()
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if (! $checkpoint || ! $checkpoint->isResumable()) {
                return null;
            }

            $checkpoint->forceFill(['status' => ToolApprovalStatus::Resolved])->save();

            return $checkpoint;
        });
    }
}

<?php

namespace Domain\Conversation\Services\Tooling\Approval;

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
    /**
     * Snapshot a paused turn. `$pendingCalls` carries every tool call in the
     * turn (auto + gated) so resume can apply per-call decisions.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @param  array<int, array<string, mixed>>  $pendingCalls
     */
    public function persist(ChatContext $context, array $llmMessages, array $pendingCalls, int $iteration): ToolApprovalCheckpoint
    {
        return ToolApprovalCheckpoint::create([
            'agent_id' => $context->requestData->agent->id,
            'conversation_external_id' => $context->requestData->conversationId,
            'assistant_message_id' => $context->assistantMessageId,
            'request_id' => $context->requestData->requestId,
            'iteration' => $iteration,
            'status' => ToolApprovalCheckpoint::STATUS_PENDING,
            'messages_snapshot' => $llmMessages,
            'pending_calls' => $pendingCalls,
            'context' => [
                'external_session_id' => $context->requestData->externalSessionId,
                'model_id' => $context->requestData->message->model->id,
                'chatter_name' => $context->requestData->chatterName,
                'chatter_user_id' => $context->requestData->chatterUserId,
                // Carried so the resume path can attribute the approve/deny
                // decision to whoever is signed in, in the governance audit.
                'actor_user_id' => $context->requestData->actorUserId,
                'actor_name' => $context->requestData->actorName,
                'user_message' => $context->payload->message,
                'query_language' => $context->queryLanguage,
                'source' => $context->requestData->source,
                'stream_sources' => $context->requestData->streamSources,
                'preface' => $context->streamedMessage,
            ],
            'expires_at' => now()->addMinutes((int) config('chatvia.agent.tools.approval_ttl_minutes', 1440)),
        ]);
    }

    /**
     * Atomically claim a checkpoint for resumption: lock the row, verify it is
     * still pending + unexpired, flip it to resolved, and return it. Returns
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

            $checkpoint->forceFill(['status' => ToolApprovalCheckpoint::STATUS_RESOLVED])->save();

            return $checkpoint;
        });
    }
}

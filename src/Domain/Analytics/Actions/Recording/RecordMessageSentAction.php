<?php

declare(strict_types=1);

namespace Domain\Analytics\Actions\Recording;

use Domain\Analytics\Data\Recording\MessageSentRecordData;
use Domain\Analytics\Enums\AnalyticsEventType;
use Domain\Analytics\Services\AnalyticsDatabase;
use Illuminate\Database\Connection;

/**
 * Records one chat message in the analytics tables, in one transaction:
 *
 *  1. the raw `analytics_events` row (idempotent — a redelivered job inserts
 *     nothing and stops here),
 *  2. the per-conversation metrics (start/end, first visitor and first AI
 *     message, message count, duration),
 *  3. the per-agent hourly rollup (messages, prompt/completion/total tokens,
 *     response time, and `conversations_started` exactly once per
 *     conversation),
 *  4. the first time this visitor session was seen.
 *
 * Unknown country / model values are stored as configured sentinels in the
 * rollup, whose unique key cannot hold NULLs.
 */
class RecordMessageSentAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    public function execute(MessageSentRecordData $message): void
    {
        $db = $this->analytics->connection();

        $db->transaction(function () use ($db, $message): void {
            if (! $this->insertEvent($db, $message)) {
                return;
            }

            $startsConversation = $this->upsertConversationMetrics($db, $message);

            $this->incrementHourlyRollup($db, $message, $startsConversation);
            $this->recordSessionFirstSeen($db, $message);
        });
    }

    /**
     * Insert the raw event. Returns false when the message was already
     * recorded (the `message_sent` partial unique index ignores the insert).
     */
    private function insertEvent(Connection $db, MessageSentRecordData $message): bool
    {
        $tokens = $message->totalTokens();

        return $db->table('analytics_events')->insertOrIgnore([
            'agent_id' => $message->agentId,
            'workspace_id' => $message->workspaceId,
            'chatter_id' => $message->chatterId,
            'event_type' => AnalyticsEventType::MessageSent->value,
            'role' => $message->role,
            'llm_external_name' => $message->llmExternalName,
            'country_code' => $message->countryCode,
            'response_time_ms' => $message->responseTimeMs,
            'prompt_tokens' => $message->promptTokens,
            'completion_tokens' => $message->completionTokens,
            'total_tokens' => $tokens ?: null,
            'conversation_external_id' => $message->conversationExternalId,
            'message_external_id' => $message->messageExternalId,
            'session_external_id' => $message->sessionExternalId,
            'occurred_at' => $message->occurredAt,
        ]) > 0;
    }

    /**
     * Fold the message into its conversation's metrics. Returns true when
     * this is the conversation's first recorded message: the `RETURNING
     * (xmax = 0)` trick tells an INSERT from an UPDATE. The first_*_at column
     * of the other role binds NULL, and LEAST(COALESCE()) keeps the earliest.
     */
    private function upsertConversationMetrics(Connection $db, MessageSentRecordData $message): bool
    {
        $rows = $db->select(<<<'SQL'
            INSERT INTO analytics_conversation_metrics (
                conversation_external_id, agent_id, workspace_id, started_at, ended_at,
                first_user_at, first_assistant_at,
                message_count, duration_seconds
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?,
                1, 0
            )
            ON CONFLICT (conversation_external_id) DO UPDATE SET
                started_at      = LEAST(analytics_conversation_metrics.started_at, EXCLUDED.started_at),
                ended_at        = GREATEST(COALESCE(analytics_conversation_metrics.ended_at, EXCLUDED.ended_at), EXCLUDED.ended_at),
                first_user_at   = LEAST(
                    COALESCE(analytics_conversation_metrics.first_user_at, EXCLUDED.first_user_at),
                    COALESCE(EXCLUDED.first_user_at, analytics_conversation_metrics.first_user_at)
                ),
                first_assistant_at = LEAST(
                    COALESCE(analytics_conversation_metrics.first_assistant_at, EXCLUDED.first_assistant_at),
                    COALESCE(EXCLUDED.first_assistant_at, analytics_conversation_metrics.first_assistant_at)
                ),
                message_count   = analytics_conversation_metrics.message_count + 1,
                duration_seconds = GREATEST(0, EXTRACT(EPOCH FROM (
                    GREATEST(COALESCE(analytics_conversation_metrics.ended_at, EXCLUDED.ended_at), EXCLUDED.ended_at)
                    - LEAST(analytics_conversation_metrics.started_at, EXCLUDED.started_at)
                ))::int)
            RETURNING (xmax = 0) AS is_new
        SQL, [
            $message->conversationExternalId,
            $message->agentId,
            $message->workspaceId,
            $message->occurredAt,
            $message->occurredAt,
            $message->isFromVisitor() ? $message->occurredAt : null,
            $message->isFromAssistant() ? $message->occurredAt : null,
        ]);

        return $rows !== [] && (bool) $rows[0]->is_new;
    }

    /**
     * Add the message to the agent's hourly rollup, bucketed by the
     * dimensions it carries (country is known now, unlike when the
     * conversation row was created).
     */
    private function incrementHourlyRollup(Connection $db, MessageSentRecordData $message, bool $startsConversation): void
    {
        $hasResponseTime = $message->responseTimeMs !== null && $message->isFromAssistant();

        $db->statement(<<<'SQL'
            INSERT INTO analytics_agent_hourly_rollups (
                agent_id, workspace_id, bucket_hour, country_code, llm_external_name,
                conversations_started,
                user_messages, assistant_messages,
                prompt_tokens, completion_tokens, total_tokens,
                response_ms_sum, response_ms_count
            ) VALUES (
                ?, ?, date_trunc('hour', ?::timestamptz), ?, ?,
                ?,
                ?, ?,
                ?, ?, ?,
                ?, ?
            )
            ON CONFLICT (agent_id, bucket_hour, country_code, llm_external_name) DO UPDATE SET
                conversations_started = analytics_agent_hourly_rollups.conversations_started + EXCLUDED.conversations_started,
                user_messages         = analytics_agent_hourly_rollups.user_messages         + EXCLUDED.user_messages,
                assistant_messages    = analytics_agent_hourly_rollups.assistant_messages    + EXCLUDED.assistant_messages,
                prompt_tokens         = analytics_agent_hourly_rollups.prompt_tokens         + EXCLUDED.prompt_tokens,
                completion_tokens     = analytics_agent_hourly_rollups.completion_tokens     + EXCLUDED.completion_tokens,
                total_tokens          = analytics_agent_hourly_rollups.total_tokens          + EXCLUDED.total_tokens,
                response_ms_sum       = analytics_agent_hourly_rollups.response_ms_sum       + EXCLUDED.response_ms_sum,
                response_ms_count     = analytics_agent_hourly_rollups.response_ms_count     + EXCLUDED.response_ms_count
        SQL, [
            $message->agentId,
            $message->workspaceId,
            $message->occurredAt,
            $message->countryCode ?? config('chatvia.analytics.unknown_country_code'),
            $message->llmExternalName ?? config('chatvia.analytics.unknown_llm_external_name'),
            $startsConversation ? 1 : 0,
            $message->isFromVisitor() ? 1 : 0,
            $message->isFromAssistant() ? 1 : 0,
            (int) $message->promptTokens,
            (int) $message->completionTokens,
            $message->totalTokens(),
            $hasResponseTime ? (int) $message->responseTimeMs : 0,
            $hasResponseTime ? 1 : 0,
        ]);
    }

    private function recordSessionFirstSeen(Connection $db, MessageSentRecordData $message): void
    {
        $db->statement(<<<'SQL'
            INSERT INTO analytics_session_firsts (agent_id, workspace_id, session_external_id, first_seen_at)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (agent_id, session_external_id) DO NOTHING
        SQL, [
            $message->agentId,
            $message->workspaceId,
            $message->sessionExternalId,
            $message->occurredAt,
        ]);
    }
}

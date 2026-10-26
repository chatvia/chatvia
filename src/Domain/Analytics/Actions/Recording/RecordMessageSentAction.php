<?php

declare(strict_types=1);

namespace Domain\Analytics\Actions\Recording;

use Domain\Analytics\Data\Recording\MessageSentRecordData;
use Domain\Analytics\Services\AnalyticsDatabase;
use Domain\Analytics\Services\ResponseTimeHistogram;
use Illuminate\Database\Connection;

/**
 * Records one chat message in the analytics tables, in one transaction:
 *
 *  1. the raw `analytics_events` row (idempotent — a redelivered job inserts
 *     nothing and stops here),
 *  2. the per-conversation metrics (start/end, first visitor and first AI
 *     message, message count, duration, channel),
 *  3. the per-agent hourly and daily rollups (messages, prompt/completion/
 *     total tokens, response time with its histogram and slowest reply, AI
 *     replies that found no answer, the replies' confidence, and
 *     `conversations_started` exactly once per conversation),
 *  4. the conversation start by country, when the country is known,
 *  5. the first time this visitor session was seen, with its device.
 *
 * The hourly rows serve short ranges and the hour-of-day patterns, the daily
 * rows long ranges and lifetime totals, so neither read grows with history.
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

            $this->incrementRollup($db, 'analytics_agent_hourly_rollups', 'bucket_hour', "date_trunc('hour', ?::timestamp)", $message->occurredAt, $message, $startsConversation);
            $this->incrementRollup($db, 'analytics_agent_daily', 'bucket_day', '?::date', $message->occurredAt->format('Y-m-d'), $message, $startsConversation);

            if ($startsConversation && filled($message->countryCode)) {
                $this->countConversationCountry($db, $message);
            }

            $this->recordSessionFirstSeen($db, $message);
        });
    }

    /**
     * Insert the raw event. Returns false when the message was already
     * recorded (the unique message index ignores the insert; a redelivered
     * job carries the same `occurred_at`, the table's partition key).
     */
    private function insertEvent(Connection $db, MessageSentRecordData $message): bool
    {
        return $db->table('analytics_events')->insertOrIgnore([
            'agent_id' => $message->agentId,
            'workspace_id' => $message->workspaceId,
            'chatter_id' => $message->chatterId,
            'role' => $message->role,
            'response_time_ms' => $message->responseTimeMs,
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
                conversation_external_id, agent_id, workspace_id, started_at, ended_at, source,
                first_user_at, first_assistant_at,
                message_count, duration_seconds
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?,
                1, 0
            )
            ON CONFLICT (conversation_external_id) DO UPDATE SET
                started_at      = LEAST(analytics_conversation_metrics.started_at, EXCLUDED.started_at),
                ended_at        = GREATEST(COALESCE(analytics_conversation_metrics.ended_at, EXCLUDED.ended_at), EXCLUDED.ended_at),
                source          = COALESCE(analytics_conversation_metrics.source, EXCLUDED.source),
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
            $message->source,
            $message->isFromVisitor() ? $message->occurredAt : null,
            $message->isFromAssistant() ? $message->occurredAt : null,
        ]);

        return $rows !== [] && (bool) $rows[0]->is_new;
    }

    /**
     * Add the message to one of the agent's rollups (hourly or daily: the
     * same counters, another bucket). The histogram is added bucket by
     * bucket, and only for a reply with a measured time.
     */
    private function incrementRollup(Connection $db, string $table, string $bucketColumn, string $bucketValue, mixed $bucket, MessageSentRecordData $message, bool $startsConversation): void
    {
        $responseTime = $message->isFromAssistant() ? $message->responseTimeMs : null;
        $confidence = $message->replyConfidence();

        $db->statement(<<<SQL
            INSERT INTO {$table} AS r (
                agent_id, workspace_id, {$bucketColumn},
                conversations_started,
                user_messages, assistant_messages,
                prompt_tokens, completion_tokens, total_tokens,
                response_ms_sum, response_ms_count, response_ms_max, response_ms_hist,
                unanswered_replies, confidence_sum, confidence_count
            ) VALUES (
                ?, ?, {$bucketValue},
                ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?::integer[],
                ?, ?, ?
            )
            ON CONFLICT (agent_id, {$bucketColumn}) DO UPDATE SET
                conversations_started = r.conversations_started + EXCLUDED.conversations_started,
                user_messages         = r.user_messages         + EXCLUDED.user_messages,
                assistant_messages    = r.assistant_messages    + EXCLUDED.assistant_messages,
                prompt_tokens         = r.prompt_tokens         + EXCLUDED.prompt_tokens,
                completion_tokens     = r.completion_tokens     + EXCLUDED.completion_tokens,
                total_tokens          = r.total_tokens          + EXCLUDED.total_tokens,
                response_ms_sum       = r.response_ms_sum       + EXCLUDED.response_ms_sum,
                response_ms_count     = r.response_ms_count     + EXCLUDED.response_ms_count,
                response_ms_max       = GREATEST(r.response_ms_max, EXCLUDED.response_ms_max),
                response_ms_hist      = CASE WHEN EXCLUDED.response_ms_count > 0
                    THEN ARRAY(SELECT a + b FROM unnest(r.response_ms_hist, EXCLUDED.response_ms_hist) WITH ORDINALITY AS h(a, b, n) ORDER BY n)
                    ELSE r.response_ms_hist
                END,
                unanswered_replies    = r.unanswered_replies    + EXCLUDED.unanswered_replies,
                confidence_sum        = r.confidence_sum        + EXCLUDED.confidence_sum,
                confidence_count      = r.confidence_count      + EXCLUDED.confidence_count
        SQL, [
            $message->agentId,
            $message->workspaceId,
            $bucket,
            $startsConversation ? 1 : 0,
            $message->isFromVisitor() ? 1 : 0,
            $message->isFromAssistant() ? 1 : 0,
            (int) $message->promptTokens,
            (int) $message->completionTokens,
            $message->totalTokens(),
            (int) $responseTime,
            $responseTime !== null ? 1 : 0,
            (int) $responseTime,
            ResponseTimeHistogram::literal(ResponseTimeHistogram::countsFor($responseTime)),
            $message->isUnanswered() ? 1 : 0,
            $confidence ?? 0,
            $confidence !== null ? 1 : 0,
        ]);
    }

    private function countConversationCountry(Connection $db, MessageSentRecordData $message): void
    {
        $db->statement(<<<'SQL'
            INSERT INTO analytics_agent_country_daily (agent_id, workspace_id, bucket_day, country_code, conversations_started)
            VALUES (?, ?, ?::date, ?, 1)
            ON CONFLICT (agent_id, bucket_day, country_code) DO UPDATE SET
                conversations_started = analytics_agent_country_daily.conversations_started + 1
        SQL, [
            $message->agentId,
            $message->workspaceId,
            $message->occurredAt->format('Y-m-d'),
            $message->countryCode,
        ]);
    }

    private function recordSessionFirstSeen(Connection $db, MessageSentRecordData $message): void
    {
        $db->statement(<<<'SQL'
            INSERT INTO analytics_session_firsts (agent_id, workspace_id, session_external_id, first_seen_at, device_type, browser, os)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (agent_id, session_external_id) DO NOTHING
        SQL, [
            $message->agentId,
            $message->workspaceId,
            $message->sessionExternalId,
            $message->occurredAt,
            $message->deviceType,
            $message->browser,
            $message->os,
        ]);
    }
}

<?php

namespace Domain\Analytics\Queries\Agent;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Activity\AgentTotalsData;

/**
 * An agent's all-time headline numbers in one round-trip: the hourly rollup
 * (messages, conversations, response time, tokens, busiest hour), the
 * per-conversation metrics (length, duration, first response), the session
 * firsts (unique visitors) and the feedback events.
 */
class AgentTotalsQuery extends AgentAnalyticsQuery
{
    private const string SQL = <<<'SQL'
WITH agent_rollup AS (
    SELECT
        COALESCE(SUM(user_messages), 0)             AS total_user,
        COALESCE(SUM(assistant_messages), 0)        AS total_assistant,
        COALESCE(SUM(conversations_started), 0)     AS total_conversations,
        CASE WHEN SUM(response_ms_count) > 0
             THEN SUM(response_ms_sum)::float / SUM(response_ms_count)
        END                                         AS avg_response_ms,
        COALESCE(SUM(total_tokens), 0)              AS total_tokens,
        CASE WHEN SUM(assistant_messages) > 0
             THEN SUM(total_tokens)::float / SUM(assistant_messages)
        END                                         AS avg_tokens,
        MIN(bucket_hour)::date                      AS first_data_date
    FROM analytics_agent_hourly_rollups
    WHERE agent_id = ?
),
hour_bucket AS (
    SELECT EXTRACT(HOUR FROM bucket_hour)::int AS hour,
           SUM(conversations_started)          AS c
    FROM analytics_agent_hourly_rollups
    WHERE agent_id = ?
    GROUP BY 1
    ORDER BY c DESC NULLS LAST
    LIMIT 1
),
-- avg_first_response_seconds reads the incrementally-maintained first_*_at pair.
conv_metrics AS (
    SELECT
        AVG(message_count)                                    AS avg_messages_per_conversation,
        AVG(NULLIF(duration_seconds, 0))                      AS avg_conversation_duration_seconds,
        AVG(EXTRACT(EPOCH FROM (first_assistant_at - first_user_at)))
            FILTER (WHERE first_assistant_at > first_user_at) AS avg_first_response_seconds
    FROM analytics_conversation_metrics
    WHERE agent_id = ?
),
session_count AS (
    SELECT COUNT(*) AS unique_sessions
    FROM analytics_session_firsts
    WHERE agent_id = ?
),
-- Feedback is current-state (one upserted row per message), so totals are a
-- plain COUNT over the live rows rather than a lifetime rollup counter.
feedback AS (
    SELECT
        COUNT(*) FILTER (WHERE feedback = 'positive')   AS positive_feedback,
        COUNT(*) FILTER (WHERE feedback = 'negative')   AS negative_feedback,
        COUNT(DISTINCT conversation_external_id)        AS conversations_with_feedback
    FROM analytics_events
    WHERE agent_id = ? AND event_type = 'feedback_given'
)
SELECT
    ar.total_assistant,
    ar.total_user,
    ar.total_conversations,
    ar.avg_response_ms,
    ar.total_tokens,
    ar.avg_tokens,
    fb.positive_feedback,
    fb.negative_feedback,
    ar.first_data_date,
    (SELECT hour FROM hour_bucket)                       AS peak_hour,
    cm.avg_messages_per_conversation,
    cm.avg_conversation_duration_seconds,
    fb.conversations_with_feedback,
    sc.unique_sessions,
    cm.avg_first_response_seconds
FROM agent_rollup ar
CROSS JOIN conv_metrics cm
CROSS JOIN session_count sc
CROSS JOIN feedback fb
SQL;

    public function get(Agent $agent): AgentTotalsData
    {
        $row = $this->analytics()->selectOne(self::SQL, array_fill(0, 5, $agent->id));

        if ($row === null) {
            return new AgentTotalsData;
        }

        return new AgentTotalsData(
            assistantMessages: (int) ($row->total_assistant ?? 0),
            userMessages: (int) ($row->total_user ?? 0),
            conversations: (int) ($row->total_conversations ?? 0),
            avgResponseMs: self::floatOrNull($row->avg_response_ms),
            totalTokens: (int) ($row->total_tokens ?? 0),
            avgTokensPerReply: self::floatOrNull($row->avg_tokens),
            positiveFeedback: (int) ($row->positive_feedback ?? 0),
            negativeFeedback: (int) ($row->negative_feedback ?? 0),
            conversationsWithFeedback: (int) ($row->conversations_with_feedback ?? 0),
            avgMessagesPerConversation: self::floatOrNull($row->avg_messages_per_conversation),
            avgConversationSeconds: self::floatOrNull($row->avg_conversation_duration_seconds),
            avgFirstResponseSeconds: self::floatOrNull($row->avg_first_response_seconds),
            peakHour: $row->peak_hour !== null ? (int) $row->peak_hour : null,
            firstDataDate: $row->first_data_date ? (string) $row->first_data_date : null,
            uniqueSessions: (int) ($row->unique_sessions ?? 0),
        );
    }

    private static function floatOrNull(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }
}

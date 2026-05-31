<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Events\MessageSentEvent;
use Illuminate\Support\Facades\DB;

class RecordMessageSentAction
{
    public function execute(MessageSentEvent $event): void
    {
        $connection = config('analytics.connection');
        $countrySentinel = config('analytics.unknown_country_code');
        $llmSentinel = config('analytics.unknown_llm_external_name');

        $db = DB::connection($connection);

        $isUser = $event->role === 'user';
        $isAssistant = $event->role === 'assistant';
        $tokens = (int) ($event->promptTokens ?? 0) + (int) ($event->completionTokens ?? 0);
        $hasResponseTime = $event->responseTimeMs !== null && $isAssistant;
        $countryCode = $event->countryCode ?? $countrySentinel;
        $llmName = $event->llmExternalName ?? $llmSentinel;

        $db->transaction(function () use (
            $db, $event, $countryCode, $llmName,
            $isUser, $isAssistant, $tokens, $hasResponseTime
        ): void {
            $db->table('analytics_events')->insert([
                'agent_id' => $event->agentId,
                'event_type' => 'message_sent',
                'role' => $event->role,
                'llm_external_name' => $event->llmExternalName,
                'country_code' => $event->countryCode,
                'response_time_ms' => $event->responseTimeMs,
                'prompt_tokens' => $event->promptTokens,
                'completion_tokens' => $event->completionTokens,
                'total_tokens' => $tokens ?: null,
                'conversation_external_id' => $event->conversationExternalId,
                'message_external_id' => $event->messageExternalId,
                'session_external_id' => $event->sessionExternalId,
                'occurred_at' => $event->occurredAt,
            ]);

            // Per-conversation metrics. The RETURNING (xmax = 0) trick tells us
            // whether this UPSERT was an INSERT (first MessageSent ever for this
            // conversation) or an UPDATE. We use that to drive the rollup's
            // conversations_started increment exactly once per conversation,
            // bucketed under the dimensions the first message carried (country
            // is known at this point, unlike at row-creation time).
            $convResult = $db->select(<<<'SQL'
                INSERT INTO analytics_conversation_metrics (
                    conversation_external_id, agent_id, started_at, ended_at,
                    message_count, duration_seconds, had_feedback
                ) VALUES (
                    ?, ?, ?, ?,
                    1, 0, FALSE
                )
                ON CONFLICT (conversation_external_id) DO UPDATE SET
                    started_at      = LEAST(analytics_conversation_metrics.started_at, EXCLUDED.started_at),
                    ended_at        = GREATEST(COALESCE(analytics_conversation_metrics.ended_at, EXCLUDED.ended_at), EXCLUDED.ended_at),
                    message_count   = analytics_conversation_metrics.message_count + 1,
                    duration_seconds = GREATEST(0, EXTRACT(EPOCH FROM (
                        GREATEST(COALESCE(analytics_conversation_metrics.ended_at, EXCLUDED.ended_at), EXCLUDED.ended_at)
                        - LEAST(analytics_conversation_metrics.started_at, EXCLUDED.started_at)
                    ))::int)
                RETURNING (xmax = 0) AS is_new
            SQL, [
                $event->conversationExternalId,
                $event->agentId,
                $event->occurredAt,
                $event->occurredAt,
            ]);
            $isFirstMessageOfConversation = ! empty($convResult) && (bool) $convResult[0]->is_new;

            $db->statement(<<<'SQL'
                INSERT INTO analytics_agent_hourly_rollups (
                    agent_id, bucket_hour, country_code, llm_external_name,
                    conversations_started,
                    user_messages, assistant_messages,
                    total_tokens, response_ms_sum, response_ms_count
                ) VALUES (
                    ?, date_trunc('hour', ?::timestamptz), ?, ?,
                    ?,
                    ?, ?,
                    ?, ?, ?
                )
                ON CONFLICT (agent_id, bucket_hour, country_code, llm_external_name) DO UPDATE SET
                    conversations_started = analytics_agent_hourly_rollups.conversations_started + EXCLUDED.conversations_started,
                    user_messages         = analytics_agent_hourly_rollups.user_messages         + EXCLUDED.user_messages,
                    assistant_messages    = analytics_agent_hourly_rollups.assistant_messages    + EXCLUDED.assistant_messages,
                    total_tokens          = analytics_agent_hourly_rollups.total_tokens          + EXCLUDED.total_tokens,
                    response_ms_sum       = analytics_agent_hourly_rollups.response_ms_sum       + EXCLUDED.response_ms_sum,
                    response_ms_count     = analytics_agent_hourly_rollups.response_ms_count     + EXCLUDED.response_ms_count
            SQL, [
                $event->agentId,
                $event->occurredAt,
                $countryCode,
                $llmName,
                $isFirstMessageOfConversation ? 1 : 0,
                $isUser ? 1 : 0,
                $isAssistant ? 1 : 0,
                $tokens,
                $hasResponseTime ? (int) $event->responseTimeMs : 0,
                $hasResponseTime ? 1 : 0,
            ]);

            $db->statement(<<<'SQL'
                INSERT INTO analytics_session_firsts (agent_id, session_external_id, first_seen_at)
                VALUES (?, ?, ?)
                ON CONFLICT (agent_id, session_external_id) DO NOTHING
            SQL, [
                $event->agentId,
                $event->sessionExternalId,
                $event->occurredAt,
            ]);
        });
    }
}

<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Events\ConversationTopicAssigned;
use Illuminate\Support\Facades\DB;

/**
 * Upserts the per-day topic rollup when a conversation is assigned a topic.
 * Increment-only and fired once per (conversation, topic): AnalyzeConversationAction
 * stamps analyzed_at exactly once and only emits this event for newly-created
 * conversation_topics rows, so a conversation counts each of its topics once
 * (multi-label conversations increment several topic rows). Buckets by the
 * conversation's own day so the trend aligns with when the conversation
 * happened, not when the (async, end-of-life) analysis ran.
 */
class RecordConversationTopicAction
{
    public function execute(ConversationTopicAssigned $event): void
    {
        $db = DB::connection(config('analytics.connection'));
        $day = $event->occurredAt->format('Y-m-d');

        $db->statement(<<<'SQL'
            INSERT INTO analytics_agent_topic_daily (agent_id, user_id, bucket_day, chat_topic_id, conversation_count)
            VALUES (?, ?, ?::date, ?, 1)
            ON CONFLICT (agent_id, bucket_day, chat_topic_id) DO UPDATE SET
                conversation_count = analytics_agent_topic_daily.conversation_count + 1
        SQL, [$event->agentId, $event->userId, $day, $event->topicId]);
    }
}

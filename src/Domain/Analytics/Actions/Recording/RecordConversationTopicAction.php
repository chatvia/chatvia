<?php

declare(strict_types=1);

namespace Domain\Analytics\Actions\Recording;

use Domain\Analytics\Actions\Analysis\AnalyzeConversationAction;
use Domain\Analytics\Data\Recording\TopicAssignmentRecordData;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * Upserts the per-day topic rollup when a conversation is assigned a topic.
 * Increment-only and fired once per (conversation, topic):
 * {@see AnalyzeConversationAction} stamps analyzed_at exactly once and only
 * emits an event for newly-created conversation_topics rows, so a conversation
 * counts each of its topics once (multi-label conversations increment several
 * topic rows). Buckets by the conversation's own day so the trend aligns with
 * when the conversation happened, not when the (async, end-of-life) analysis ran.
 */
class RecordConversationTopicAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    public function execute(TopicAssignmentRecordData $assignment): void
    {
        $this->analytics->connection()->statement(<<<'SQL'
            INSERT INTO analytics_agent_topic_daily (agent_id, workspace_id, bucket_day, topic_id, conversation_count)
            VALUES (?, ?, ?::date, ?, 1)
            ON CONFLICT (agent_id, bucket_day, topic_id) DO UPDATE SET
                conversation_count = analytics_agent_topic_daily.conversation_count + 1
        SQL, [$assignment->agentId, $assignment->workspaceId, $assignment->occurredAt->format('Y-m-d'), $assignment->topicId]);
    }
}

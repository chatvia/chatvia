<?php

namespace Domain\Analytics\Actions\Recording;

use Domain\Analytics\Data\Recording\MessageAnalysisRecordData;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * Upserts the per-day sentiment and tone rollups for a single analyzed user
 * message. Increment-only: AnalyzeMessageAction writes sentiment/tone once per
 * message (it early-returns when both are already set), so a message is never
 * double-counted. Buckets by the message's own day so late analysis still
 * lands in the correct date.
 */
class RecordMessageAnalyzedAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    public function execute(MessageAnalysisRecordData $analysis): void
    {
        if ($analysis->sentiment === null && $analysis->tone === null) {
            return;
        }

        $db = $this->analytics->connection();
        $day = $analysis->occurredAt->format('Y-m-d');

        $db->transaction(function () use ($db, $analysis, $day): void {
            if ($analysis->sentiment !== null) {
                $db->statement(<<<'SQL'
                    INSERT INTO analytics_agent_sentiment_daily (agent_id, workspace_id, bucket_day, sentiment, message_count)
                    VALUES (?, ?, ?::date, ?, 1)
                    ON CONFLICT (agent_id, bucket_day, sentiment) DO UPDATE SET
                        message_count = analytics_agent_sentiment_daily.message_count + 1
                SQL, [$analysis->agentId, $analysis->workspaceId, $day, $analysis->sentiment]);
            }

            if ($analysis->tone !== null) {
                $db->statement(<<<'SQL'
                    INSERT INTO analytics_agent_tone_daily (agent_id, workspace_id, bucket_day, tone, message_count)
                    VALUES (?, ?, ?::date, ?, 1)
                    ON CONFLICT (agent_id, bucket_day, tone) DO UPDATE SET
                        message_count = analytics_agent_tone_daily.message_count + 1
                SQL, [$analysis->agentId, $analysis->workspaceId, $day, $analysis->tone]);
            }
        });
    }
}

<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Events\MessageAnalyzed;
use Illuminate\Support\Facades\DB;

/**
 * Upserts the per-day sentiment and tone rollups for a single analyzed user
 * message. Increment-only: AnalyzeMessageAction writes sentiment/tone once per
 * message (it early-returns when both are already set), so a message is never
 * double-counted. Buckets by the message's own day so late analysis still
 * lands in the correct date.
 */
class RecordMessageAnalyzedAction
{
    public function execute(MessageAnalyzed $event): void
    {
        if ($event->sentiment === null && $event->tone === null) {
            return;
        }

        $db = DB::connection(config('chatvia.analytics.connection'));
        $day = $event->occurredAt->format('Y-m-d');

        $db->transaction(function () use ($db, $event, $day): void {
            if ($event->sentiment !== null) {
                $db->statement(<<<'SQL'
                    INSERT INTO analytics_agent_sentiment_daily (agent_id, workspace_id, bucket_day, sentiment, message_count)
                    VALUES (?, ?, ?::date, ?, 1)
                    ON CONFLICT (agent_id, bucket_day, sentiment) DO UPDATE SET
                        message_count = analytics_agent_sentiment_daily.message_count + 1
                SQL, [$event->agentId, $event->workspaceId, $day, $event->sentiment]);
            }

            if ($event->tone !== null) {
                $db->statement(<<<'SQL'
                    INSERT INTO analytics_agent_tone_daily (agent_id, workspace_id, bucket_day, tone, message_count)
                    VALUES (?, ?, ?::date, ?, 1)
                    ON CONFLICT (agent_id, bucket_day, tone) DO UPDATE SET
                        message_count = analytics_agent_tone_daily.message_count + 1
                SQL, [$event->agentId, $event->workspaceId, $day, $event->tone]);
            }
        });
    }
}

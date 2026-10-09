<?php

namespace Domain\Analytics\Actions\Recording;

use Domain\Analytics\Data\Recording\FeedbackRecordData;
use Domain\Analytics\Enums\AnalyticsEventType;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * Records message feedback as CURRENT STATE: exactly one row per message in
 * analytics_events (event_type = 'feedback_given'), upserted on
 * message_external_id. Switching a reaction (👍 → 👎) overwrites the same row;
 * clearing it (👍 → none) deletes the row. Feedback totals are then a plain
 * COUNT over the current rows — no prior value, no lifetime counters, and no
 * double-counting when a visitor toggles or switches their reaction.
 */
class RecordFeedbackGivenAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    public function execute(FeedbackRecordData $feedback): void
    {
        // Cleared reaction: drop the message's feedback row so it leaves the
        // current-snapshot counts entirely.
        if ($feedback->feedback === null) {
            $this->analytics->table('analytics_events')
                ->where('event_type', AnalyticsEventType::FeedbackGiven->value)
                ->where('message_external_id', $feedback->messageExternalId)
                ->delete();

            return;
        }

        // One row per message: a new reaction inserts, a switch updates in place.
        $this->analytics->connection()->statement(<<<'SQL'
            INSERT INTO analytics_events (
                agent_id, workspace_id, chatter_user_id, event_type, feedback, llm_external_name, country_code,
                conversation_external_id, message_external_id, occurred_at
            ) VALUES (?, ?, ?, 'feedback_given', ?, ?, ?, ?, ?, ?)
            ON CONFLICT (message_external_id) WHERE event_type = 'feedback_given'
            DO UPDATE SET
                feedback          = EXCLUDED.feedback,
                llm_external_name = EXCLUDED.llm_external_name,
                country_code      = EXCLUDED.country_code,
                occurred_at       = EXCLUDED.occurred_at
        SQL, [
            $feedback->agentId,
            $feedback->workspaceId,
            $feedback->chatterUserId,
            $feedback->feedback,
            $feedback->llmExternalName,
            $feedback->countryCode,
            $feedback->conversationExternalId,
            $feedback->messageExternalId,
            $feedback->occurredAt,
        ]);
    }
}

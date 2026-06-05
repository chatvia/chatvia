<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Events\FeedbackGiven;
use Illuminate\Support\Facades\DB;

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
    public function execute(FeedbackGiven $event): void
    {
        $db = DB::connection(config('analytics.connection'));

        // Cleared reaction: drop the message's feedback row so it leaves the
        // current-snapshot counts entirely.
        if ($event->feedback === null) {
            $db->table('analytics_events')
                ->where('event_type', 'feedback_given')
                ->where('message_external_id', $event->messageExternalId)
                ->delete();

            return;
        }

        // One row per message: a new reaction inserts, a switch updates in place.
        $db->statement(<<<'SQL'
            INSERT INTO analytics_events (
                agent_id, user_id, event_type, feedback, llm_external_name, country_code,
                conversation_external_id, message_external_id, occurred_at
            ) VALUES (?, ?, 'feedback_given', ?, ?, ?, ?, ?, ?)
            ON CONFLICT (message_external_id) WHERE event_type = 'feedback_given'
            DO UPDATE SET
                feedback          = EXCLUDED.feedback,
                llm_external_name = EXCLUDED.llm_external_name,
                country_code      = EXCLUDED.country_code,
                occurred_at       = EXCLUDED.occurred_at
        SQL, [
            $event->agentId,
            $event->userId,
            $event->feedback,
            $event->llmExternalName,
            $event->countryCode,
            $event->conversationExternalId,
            $event->messageExternalId,
            $event->occurredAt,
        ]);
    }
}

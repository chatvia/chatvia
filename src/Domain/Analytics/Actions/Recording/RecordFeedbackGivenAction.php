<?php

declare(strict_types=1);

namespace Domain\Analytics\Actions\Recording;

use Domain\Analytics\Data\Recording\FeedbackRecordData;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * Records message feedback as CURRENT STATE: exactly one row per message in
 * analytics_message_feedback, keyed by message_external_id. Switching a
 * reaction (👍 → 👎) overwrites the row; clearing it (👍 → none) deletes it.
 * Feedback totals are then a plain COUNT over the current rows — no prior
 * value, no lifetime counters, and no double-counting when a visitor toggles
 * or switches their reaction. It lives apart from the append-only event log,
 * which is split into monthly partitions.
 */
class RecordFeedbackGivenAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    public function execute(FeedbackRecordData $feedback): void
    {
        // Cleared reaction: drop the message's row so it leaves the counts.
        if ($feedback->feedback === null) {
            $this->analytics->table('analytics_message_feedback')
                ->where('message_external_id', $feedback->messageExternalId)
                ->delete();

            return;
        }

        $this->analytics->table('analytics_message_feedback')->upsert(
            [[
                'message_external_id' => $feedback->messageExternalId,
                'agent_id' => $feedback->agentId,
                'workspace_id' => $feedback->workspaceId,
                'chatter_id' => $feedback->chatterId,
                'conversation_external_id' => $feedback->conversationExternalId,
                'feedback' => $feedback->feedback,
                'occurred_at' => $feedback->occurredAt,
            ]],
            uniqueBy: ['message_external_id'],
            update: ['feedback', 'occurred_at'],
        );
    }
}

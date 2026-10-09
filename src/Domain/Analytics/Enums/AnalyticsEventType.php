<?php

declare(strict_types=1);

namespace Domain\Analytics\Enums;

/**
 * The kinds of row in `analytics_events` (its `event_type` column). Stored
 * values — never rename them.
 *
 *  - MessageSent: one row per chat message (visitor or AI), insert-only.
 *  - FeedbackGiven: the CURRENT thumbs reaction on one message — upserted on
 *    change and deleted when cleared, so it is never pruned by age.
 */
enum AnalyticsEventType: string
{
    case MessageSent = 'message_sent';
    case FeedbackGiven = 'feedback_given';
}

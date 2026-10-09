<?php

namespace Domain\Analytics\Data\Account\Activity;

use Domain\Analytics\Data\Account\DailyCountData;

/**
 * Conversation-level numbers over a window: how many started, their average
 * length and duration, and the busiest day (null when there was none).
 */
final readonly class ConversationStatsData
{
    public function __construct(
        public int $conversations,
        public int|float|null $avgMessagesPerConversation,
        public int|float|null $avgDurationSeconds,
        public ?DailyCountData $busiestDay,
    ) {}
}

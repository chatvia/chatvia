<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account\Activity;

use Domain\Analytics\Data\Account\FeedbackTallyData;

/**
 * A one-shot snapshot of an account (or one agent) over a window. This
 * month's message usage and limit come from the account's monthly message
 * limit and are null when it has none.
 */
final readonly class AccountOverviewData
{
    public function __construct(
        public int $incomingMessages,
        public int $aiMessages,
        public int $conversations,
        public int $uniqueVisitors,
        public int $leads,
        /** Average AI reply time in ms, rounded; null when no reply was timed. */
        public int|float|null $avgResponseMs,
        public FeedbackTallyData $feedback,
        public int $tokens,
        public ?int $messagesUsedThisMonth,
        public ?int $messagesLimitThisMonth,
    ) {}

    public function totalMessages(): int
    {
        return $this->incomingMessages + $this->aiMessages;
    }
}

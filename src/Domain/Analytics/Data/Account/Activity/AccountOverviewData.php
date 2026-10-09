<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account\Activity;

use Domain\Analytics\Data\Account\FeedbackTallyData;

/**
 * A one-shot snapshot of an account (or one agent) over a window. The
 * this-cycle message usage and limit come from the billing cycle and are
 * null when the account has no active cycle.
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
        public ?int $messagesUsedThisCycle,
        public ?int $messagesLimitThisCycle,
    ) {}

    public function totalMessages(): int
    {
        return $this->incomingMessages + $this->aiMessages;
    }
}

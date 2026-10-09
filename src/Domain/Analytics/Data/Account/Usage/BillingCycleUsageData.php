<?php

namespace Domain\Analytics\Data\Account\Usage;

use Carbon\CarbonImmutable;

/**
 * The current billing cycle's message allowance: the window, the limit, what
 * is used and left, add-on messages, tokens, and a straight-line projection of
 * where usage lands by the cycle end.
 */
final readonly class BillingCycleUsageData
{
    public function __construct(
        public CarbonImmutable $cycleStart,
        public CarbonImmutable $cycleEnd,
        public int $daysTotal,
        public int $daysLeft,
        public int $messagesLimit,
        public int $messagesUsed,
        public int $messagesRemaining,
        public int $messagesExceeded,
        public int $addonMessages,
        public int $tokensUsed,
        public int $projectedMessagesUsed,
    ) {}
}

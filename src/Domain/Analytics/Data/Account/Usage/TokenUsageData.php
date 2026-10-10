<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account\Usage;

use Domain\Analytics\Data\Account\DailyCountData;

/**
 * Tokens consumed over a window. Reported for analytics only — message limits
 * count replies, not tokens.
 */
final readonly class TokenUsageData
{
    /**
     * @param  list<DailyCountData>  $daily  Tokens per day, oldest first.
     */
    public function __construct(
        public int $total,
        public int $prompt,
        public int $completion,
        public int $avgPerReply,
        public array $daily,
    ) {}
}

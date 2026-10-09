<?php

namespace Domain\Analytics\Data\Account\Activity;

use Domain\Analytics\Data\Account\DailyCountData;

/**
 * Message volume over a window, split into visitor messages and AI replies,
 * compared with the equally long window before it.
 */
final readonly class MessageStatsData
{
    /**
     * @param  list<DailyCountData>  $daily  Messages per day, oldest first.
     */
    public function __construct(
        public int $incoming,
        public int $outgoing,
        public int $previousTotal,
        /** Percent change against the previous window; null when it had none. */
        public ?float $changePct,
        public int|float|null $avgPerConversation,
        public array $daily,
    ) {}

    public function total(): int
    {
        return $this->incoming + $this->outgoing;
    }
}

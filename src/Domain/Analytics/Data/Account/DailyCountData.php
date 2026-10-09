<?php

namespace Domain\Analytics\Data\Account;

/**
 * One day of a per-day series: the date (YYYY-MM-DD) and its count.
 */
final readonly class DailyCountData
{
    public function __construct(
        public string $date,
        public int $count,
    ) {}
}

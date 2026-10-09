<?php

namespace Domain\Analytics\Data\Agent\Activity;

/**
 * One day of the week (0 = Sunday … 6 = Saturday, UTC) and its count.
 */
final readonly class WeekdayCountData
{
    public function __construct(
        public int $weekday,
        public int $count,
    ) {}
}

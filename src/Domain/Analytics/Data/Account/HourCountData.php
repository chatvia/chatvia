<?php

namespace Domain\Analytics\Data\Account;

/**
 * One hour of the day (0–23, UTC) and its count.
 */
final readonly class HourCountData
{
    public function __construct(
        public int $hour,
        public int $count,
    ) {}
}

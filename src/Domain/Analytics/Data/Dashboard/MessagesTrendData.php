<?php

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * AI replies this calendar month vs last month, and the change in percent
 * (100 when last month had none).
 */
class MessagesTrendData extends Data
{
    public function __construct(
        public int $this_month,
        public int $last_month,
        public int|float $percent_change,
    ) {}

    public static function none(): self
    {
        return new self(this_month: 0, last_month: 0, percent_change: 0);
    }
}

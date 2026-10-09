<?php

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * AI replies on one day, with the short label the activity chart prints.
 */
class DailyVolumeData extends Data
{
    public function __construct(
        public string $date,
        public string $label,
        public int $value,
    ) {}
}

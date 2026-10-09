<?php

namespace Domain\Analytics\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Inclusive [start, end] date range for analytics queries.
 *
 *  - `start` is at 00:00 of its day
 *  - `end` is at 23:59:59 of its day
 *
 * Missing dates fall back to the default window: the last
 * {@see self::DEFAULT_DAYS} days including today.
 */
class DateRangeData extends Data
{
    /**
     * Default range length in days, INCLUSIVE — today plus the previous 6
     * gives 7 daily buckets.
     */
    public const int DEFAULT_DAYS = 7;

    public function __construct(
        public Carbon $start,
        public Carbon $end,
    ) {}

    /**
     * Build a range from optional `Y-m-d` (or any Carbon-parsable) dates.
     */
    public static function fromDates(?string $start, ?string $end, int $days = self::DEFAULT_DAYS): self
    {
        return new self(
            $start !== null && $start !== '' ? Carbon::parse($start)->startOfDay() : now()->subDays(max(0, $days - 1))->startOfDay(),
            $end !== null && $end !== '' ? Carbon::parse($end)->endOfDay() : now()->endOfDay(),
        );
    }

    /**
     * Canonical default range start for callers with no explicit `start_date`:
     * the first day (at 00:00) of the inclusive default window.
     */
    public static function defaultStart(): Carbon
    {
        return now()->subDays(self::DEFAULT_DAYS - 1)->startOfDay();
    }
}

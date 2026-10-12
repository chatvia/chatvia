<?php

declare(strict_types=1);

namespace Domain\Analytics\Enums;

use Carbon\CarbonImmutable;

/**
 * How finely an activity chart splits its window: by hour for a day or two,
 * by day up to about a quarter, by month beyond. The value is the Postgres
 * `date_trunc` unit.
 */
enum TimeBucket: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Month = 'month';

    /** Longest window, in days, still charted by the hour. */
    private const int MAX_HOURLY_DAYS = 2;

    /** Longest window, in days, still charted by the day. */
    private const int MAX_DAILY_DAYS = 92;

    public static function spanning(CarbonImmutable $start, CarbonImmutable $end): self
    {
        $days = (int) $start->startOfDay()->diffInDays($end->endOfDay()) + 1;

        return match (true) {
            $days <= self::MAX_HOURLY_DAYS => self::Hour,
            $days <= self::MAX_DAILY_DAYS => self::Day,
            default => self::Month,
        };
    }

    /** The start of the bucket a moment falls in. */
    public function floor(CarbonImmutable $moment): CarbonImmutable
    {
        return match ($this) {
            self::Hour => $moment->startOfHour(),
            self::Day => $moment->startOfDay(),
            self::Month => $moment->startOfMonth(),
        };
    }

    /** The start of the bucket after the one starting at `$bucketStart`. */
    public function next(CarbonImmutable $bucketStart): CarbonImmutable
    {
        return match ($this) {
            self::Hour => $bucketStart->addHour(),
            self::Day => $bucketStart->addDay(),
            self::Month => $bucketStart->addMonthNoOverflow(),
        };
    }

    /** The short label a chart prints under the bucket. */
    public function label(CarbonImmutable $bucketStart): string
    {
        return match ($this) {
            self::Hour => $bucketStart->format('H:i'),
            self::Day => $bucketStart->format('M j'),
            self::Month => $bucketStart->format('M Y'),
        };
    }
}

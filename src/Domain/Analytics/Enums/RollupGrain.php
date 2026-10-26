<?php

declare(strict_types=1);

namespace Domain\Analytics\Enums;

use Domain\Analytics\Services\Period;
use Illuminate\Database\Query\Builder;

/**
 * Which of the agent rollups a range is summed from. Both hold the same
 * counters: the hourly one (`analytics_agent_hourly_rollups`) for ranges up
 * to 92 days and for the hour-of-day patterns, the daily one
 * (`analytics_agent_daily`) beyond, so a long range or a lifetime total
 * reads one row per agent and day instead of one per hour.
 *
 * A daily range counts whole days: it starts at its first day's midnight.
 */
enum RollupGrain
{
    case Hourly;
    case Daily;

    /** The longest range, in days, still summed from the hourly rollup. */
    public const int MAX_HOURLY_DAYS = 92;

    public static function for(Period $period): self
    {
        return $period->days() <= self::MAX_HOURLY_DAYS ? self::Hourly : self::Daily;
    }

    public function table(): string
    {
        return match ($this) {
            self::Hourly => 'analytics_agent_hourly_rollups',
            self::Daily => 'analytics_agent_daily',
        };
    }

    /** The SQL of a row's start (a `timestamp`), for `date_trunc()`. */
    public function moment(): string
    {
        return match ($this) {
            self::Hourly => 'bucket_hour',
            self::Daily => 'bucket_day::timestamp',
        };
    }

    /** The SQL of a row's day (a `date`). */
    public function day(): string
    {
        return match ($this) {
            self::Hourly => 'bucket_hour::date',
            self::Daily => 'bucket_day',
        };
    }

    /** Keep the rows of the period: its hours, or its days. */
    public function within(Builder $query, Period $period): Builder
    {
        return match ($this) {
            self::Hourly => $query->whereBetween('bucket_hour', [$period->start->startOfHour(), $period->end]),
            self::Daily => $query->whereBetween('bucket_day', [$period->start->toDateString(), $period->end->toDateString()]),
        };
    }
}

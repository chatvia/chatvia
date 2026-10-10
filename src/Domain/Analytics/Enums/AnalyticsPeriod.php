<?php

namespace Domain\Analytics\Enums;

/**
 * The named time windows the account-analytics tools accept as `period`:
 * calendar or rolling windows. The case order is the order shown to the
 * model.
 */
enum AnalyticsPeriod: string
{
    case ThisMonth = 'this_month';
    case Today = 'today';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case LastMonth = 'last_month';
    case AllTime = 'all_time';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $period): string => $period->value, self::cases());
    }
}

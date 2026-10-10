<?php

namespace Domain\Analytics\Services;

use Carbon\CarbonImmutable;
use Domain\Analytics\Data\Account\AnalyticsFilterData;
use Domain\Analytics\Enums\AnalyticsPeriod;

/**
 * Turns an analytics filter's `period` (plus optional explicit start/end
 * dates, which win when both are given) into a concrete {@see Period}. The
 * default window is `this_month`.
 */
class AnalyticsPeriodResolver
{
    public function resolve(AnalyticsFilterData $filter): Period
    {
        if (filled($filter->startDate) && filled($filter->endDate)) {
            return new Period(
                CarbonImmutable::parse($filter->startDate)->startOfDay(),
                CarbonImmutable::parse($filter->endDate)->endOfDay(),
                'the selected range',
            );
        }

        $now = CarbonImmutable::now();
        $named = $filter->period ?? AnalyticsPeriod::ThisMonth;

        return match ($named) {
            AnalyticsPeriod::Today => new Period($now->startOfDay(), $now, 'today', $named),
            AnalyticsPeriod::Last7Days => new Period($now->subDays(6)->startOfDay(), $now, 'the last 7 days', $named),
            AnalyticsPeriod::Last30Days => new Period($now->subDays(29)->startOfDay(), $now, 'the last 30 days', $named),
            AnalyticsPeriod::ThisMonth => new Period($now->startOfMonth(), $now, 'this month', $named),
            AnalyticsPeriod::LastMonth => new Period(
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
                'last month',
                $named,
            ),
            AnalyticsPeriod::AllTime => new Period(CarbonImmutable::create(2020, 1, 1), $now, 'all time', $named),
        };
    }
}

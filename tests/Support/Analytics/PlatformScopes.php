<?php

declare(strict_types=1);

namespace Tests\Support\Analytics;

use Carbon\CarbonImmutable;
use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Analytics\Services\Period;
use InvalidArgumentException;

/**
 * The scope of a platform-analytics question (the admin panel's dashboard),
 * built the way the panel builds it: a named window ending now, over every
 * workspace or one.
 */
final class PlatformScopes
{
    public static function of(AnalyticsPeriod $period = AnalyticsPeriod::ThisMonth, ?int $workspaceId = null): PlatformScopeData
    {
        $now = CarbonImmutable::now();

        $window = match ($period) {
            AnalyticsPeriod::Today => new Period($now->startOfDay(), $now, 'today', $period),
            AnalyticsPeriod::Last7Days => new Period($now->subDays(6)->startOfDay(), $now, 'the last 7 days', $period),
            AnalyticsPeriod::ThisMonth => new Period($now->startOfMonth(), $now, 'this month', $period),
            AnalyticsPeriod::AllTime => new Period(CarbonImmutable::create(2020, 1, 1), $now, 'all time', $period),
            default => throw new InvalidArgumentException("No test window for {$period->value}."),
        };

        return new PlatformScopeData($window, $workspaceId);
    }
}

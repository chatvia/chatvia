<?php

declare(strict_types=1);

namespace App\Admin\Pages;

use Domain\Analytics\Data\Account\AnalyticsFilterData;
use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Analytics\Services\AnalyticsPeriodResolver;
use Illuminate\Support\Str;

/**
 * The admin dashboard's filters — a period (or a custom date range) and
 * optionally one workspace — and how they become the scope every dashboard
 * widget reads. The page and each widget resolve the same raw filter state
 * through here, so they always agree.
 */
final class DashboardFilters
{
    public const string PERIOD = 'period';

    public const string START_DATE = 'start_date';

    public const string END_DATE = 'end_date';

    public const string WORKSPACE = 'workspace';

    /** The period option that shows the two date fields. */
    public const string CUSTOM = 'custom';

    /**
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        return [
            AnalyticsPeriod::Today->value => 'Today',
            AnalyticsPeriod::Last7Days->value => 'Last 7 days',
            AnalyticsPeriod::Last30Days->value => 'Last 30 days',
            AnalyticsPeriod::ThisMonth->value => 'This month',
            AnalyticsPeriod::LastMonth->value => 'Last month',
            AnalyticsPeriod::AllTime->value => 'All time',
            self::CUSTOM => 'Custom range',
        ];
    }

    /**
     * A custom range needs both dates; until then it falls back to this
     * month. Dates entered the wrong way round are swapped.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function scope(?array $filters): PlatformScopeData
    {
        $filters ??= [];
        $start = self::date($filters[self::START_DATE] ?? null);
        $end = self::date($filters[self::END_DATE] ?? null);
        $isCustom = ($filters[self::PERIOD] ?? null) === self::CUSTOM && $start !== null && $end !== null;

        if ($isCustom && $start > $end) {
            [$start, $end] = [$end, $start];
        }

        $period = app(AnalyticsPeriodResolver::class)->resolve(new AnalyticsFilterData(
            period: AnalyticsPeriod::tryFrom((string) ($filters[self::PERIOD] ?? '')),
            startDate: $isCustom ? $start : null,
            endDate: $isCustom ? $end : null,
        ));

        return new PlatformScopeData($period, self::workspaceId($filters));
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function workspaceId(?array $filters): ?int
    {
        $workspace = $filters[self::WORKSPACE] ?? null;

        return is_numeric($workspace) ? (int) $workspace : null;
    }

    /** A `Y-m-d` date from the date picker, or null. */
    private static function date(mixed $value): ?string
    {
        return filled($value) && Str::isMatch('/^\d{4}-\d{2}-\d{2}/', $value)
            ? Str::substr($value, 0, 10)
            : null;
    }
}

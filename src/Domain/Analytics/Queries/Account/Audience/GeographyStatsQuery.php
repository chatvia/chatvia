<?php

namespace Domain\Analytics\Queries\Account\Audience;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Audience\CountryShareData;
use Domain\Analytics\Data\Account\Audience\GeographyStatsData;
use Domain\Analytics\Data\Account\LabelCountData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Where visitors come from — top countries by conversations — and what they
 * chat on: device type, browser and operating system. Countries come from the
 * daily conversation starts by country; the devices from the sessions first
 * seen in the window, all three breakdowns in one grouped pass.
 */
class GeographyStatsQuery extends AccountAnalyticsQuery
{
    /** How many entries each device / browser / OS breakdown lists. */
    private const int CLIENT_BREAKDOWN_LIMIT = 8;

    /** The session columns broken down. */
    private const array CLIENT_COLUMNS = ['device_type', 'browser', 'os'];

    public function get(AnalyticsScopeData $scope, int $limit): GeographyStatsData
    {
        $clients = $this->clientBreakdowns($scope);

        return new GeographyStatsData(
            countries: $this->countries($scope, $limit),
            devices: $clients['device_type'],
            browsers: $clients['browser'],
            operatingSystems: $clients['os'],
        );
    }

    /**
     * @return list<CountryShareData>
     */
    private function countries(AnalyticsScopeData $scope, int $limit): array
    {
        $rows = $this->dailyRollup($scope, 'analytics_agent_country_daily')
            ->groupBy('country_code')
            ->selectRaw('country_code, SUM(conversations_started) conversations')
            ->havingRaw('SUM(conversations_started) > 0')
            ->orderByRaw('SUM(conversations_started) DESC')
            ->limit($limit)
            ->get();

        $names = DB::table('countries')
            ->whereIn('code', $rows->pluck('country_code')->map(fn ($code): string => Str::lower((string) $code))->all())
            ->pluck('name', 'code');

        $total = (int) $rows->sum('conversations');

        return $rows->map(fn (object $row): CountryShareData => new CountryShareData(
            code: Str::upper((string) $row->country_code),
            name: $names[Str::lower((string) $row->country_code)] ?? Str::upper((string) $row->country_code),
            conversations: (int) $row->conversations,
            pct: $this->pct((int) $row->conversations, $total),
        ))->all();
    }

    /**
     * Sessions by device type, browser and OS, busiest first: one GROUPING
     * SETS pass, split by which column each row was grouped on.
     *
     * @return array<string, list<LabelCountData>>
     */
    private function clientBreakdowns(AnalyticsScopeData $scope): array
    {
        $rows = $this->sessionFirsts($scope)
            ->selectRaw('device_type, browser, os, GROUPING(device_type) g_device_type, GROUPING(browser) g_browser, GROUPING(os) g_os, COUNT(*) total')
            ->groupByRaw('GROUPING SETS ((device_type), (browser), (os))')
            ->get();

        return collect(self::CLIENT_COLUMNS)
            ->mapWithKeys(fn (string $column): array => [$column => $rows
                ->filter(fn (object $row): bool => (int) $row->{"g_{$column}"} === 0 && filled($row->{$column}))
                ->sortBy([['total', 'desc'], [$column, 'asc']])
                ->take(self::CLIENT_BREAKDOWN_LIMIT)
                ->map(fn (object $row): LabelCountData => new LabelCountData((string) $row->{$column}, (int) $row->total))
                ->values()
                ->all()])
            ->all();
    }
}

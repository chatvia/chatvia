<?php

namespace Domain\Analytics\Queries\Account\Audience;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Audience\CountryShareData;
use Domain\Analytics\Data\Account\Audience\GeographyStatsData;
use Domain\Analytics\Data\Account\LabelCountData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Illuminate\Support\Facades\DB;

/**
 * Where visitors come from — top countries by conversations — and what they
 * chat on: device type, browser and operating system.
 */
class GeographyStatsQuery extends AccountAnalyticsQuery
{
    /** How many entries each device / browser / OS breakdown lists. */
    private const int CLIENT_BREAKDOWN_LIMIT = 8;

    public function get(AnalyticsScopeData $scope, int $limit): GeographyStatsData
    {
        return new GeographyStatsData(
            countries: $this->countries($scope, $limit),
            devices: $this->clientBreakdown($scope, 'device_type'),
            browsers: $this->clientBreakdown($scope, 'browser'),
            operatingSystems: $this->clientBreakdown($scope, 'os'),
        );
    }

    /**
     * @return list<CountryShareData>
     */
    private function countries(AnalyticsScopeData $scope, int $limit): array
    {
        $rows = $this->hourlyRollups($scope)
            ->where('country_code', '!=', config('chatvia.analytics.unknown_country_code', '--'))
            ->groupBy('country_code')
            ->selectRaw('country_code, SUM(conversations_started) conversations')
            ->havingRaw('SUM(conversations_started) > 0')
            ->orderByRaw('SUM(conversations_started) DESC')
            ->limit($limit)
            ->get();

        $names = DB::table('countries')
            ->whereIn('code', $rows->pluck('country_code')->map(fn ($code): string => strtolower((string) $code))->all())
            ->pluck('name', 'code');

        $total = (int) $rows->sum('conversations');

        return $rows->map(fn (object $row): CountryShareData => new CountryShareData(
            code: strtoupper((string) $row->country_code),
            name: $names[strtolower((string) $row->country_code)] ?? strtoupper((string) $row->country_code),
            conversations: (int) $row->conversations,
            pct: $this->pct((int) $row->conversations, $total),
        ))->all();
    }

    /**
     * Sessions started in the window, counted per value of one client column.
     *
     * @param  'device_type'|'browser'|'os'  $column
     * @return list<LabelCountData>
     */
    private function clientBreakdown(AnalyticsScopeData $scope, string $column): array
    {
        return DB::table('chat_sessions')
            ->whereIn('agent_id', $scope->agentIds)
            ->whereBetween('created_at', [$scope->period->start, $scope->period->end])
            ->whereNotNull($column)
            ->groupBy($column)
            ->selectRaw("{$column} as label, COUNT(*) total")
            ->orderByDesc('total')
            ->limit(self::CLIENT_BREAKDOWN_LIMIT)
            ->get()
            ->map(static fn (object $row): LabelCountData => new LabelCountData((string) $row->label, (int) $row->total))
            ->all();
    }
}

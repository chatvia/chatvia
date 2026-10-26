<?php

namespace Domain\Analytics\Queries\Agent;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Account\Audience\CountryShareData;
use Illuminate\Support\Str;
use Support\Localization\Country\Models\Country;

/**
 * Where an agent's conversations come from, all time: conversations per
 * visitor country and each country's share of the conversations with a known
 * country, busiest first.
 */
class AgentCountryShareQuery extends AgentAnalyticsQuery
{
    /**
     * @return list<CountryShareData>
     */
    public function get(Agent $agent): array
    {
        return $this->cached($agent, 'countries', 'lifetime', fn (): array => $this->compute($agent));
    }

    /**
     * @return list<CountryShareData>
     */
    private function compute(Agent $agent): array
    {
        $rows = $this->dailyRollup($agent, 'analytics_agent_country_daily')
            ->selectRaw(
                'country_code,
                 SUM(conversations_started) as conversation_count,
                 ROUND(SUM(conversations_started) * 100.0 / NULLIF(SUM(SUM(conversations_started)) OVER (), 0), 1) as percentage',
            )
            ->groupBy('country_code')
            ->havingRaw('SUM(conversations_started) > 0')
            ->orderByRaw('SUM(conversations_started) DESC')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // The rollup carries only the ISO code; names live on the main DB.
        // countries.code is stored lowercase, so look up in the original case
        // and uppercase only at the boundary.
        $names = Country::query()
            ->whereIn('code', $rows->pluck('country_code')->map(fn ($code): string => (string) $code)->all())
            ->pluck('name', 'code');

        return $rows->map(static fn (object $row): CountryShareData => new CountryShareData(
            code: Str::upper((string) $row->country_code),
            name: $names[(string) $row->country_code] ?? Str::upper((string) $row->country_code),
            conversations: (int) $row->conversation_count,
            pct: (float) $row->percentage,
        ))->all();
    }
}

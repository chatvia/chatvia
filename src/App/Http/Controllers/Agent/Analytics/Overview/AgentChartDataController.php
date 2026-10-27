<?php

namespace App\Http\Controllers\Agent\Analytics\Overview;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\MessageChartResourceData;
use Domain\Analytics\Queries\Agent\AgentDailyCountsQuery;
use Illuminate\Http\Request;

/**
 * Daily message counts for the overview chart over the last 7, 14 or 30 days.
 */
class AgentChartDataController
{
    public function __invoke(Request $request, Agent $agent, AgentDailyCountsQuery $daily): MessageChartResourceData
    {
        $days = $request->integer('days', 7);

        if (! collect([7, 14, 30])->containsStrict($days)) {
            $days = 7;
        }

        $counts = $daily->replies($agent, now()->subDays($days - 1)->startOfDay(), now()->endOfDay());

        return MessageChartResourceData::fromDailyCounts($counts, $days);
    }
}

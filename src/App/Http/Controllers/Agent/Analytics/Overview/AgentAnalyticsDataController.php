<?php

namespace App\Http\Controllers\Agent\Analytics\Overview;

use App\Http\Requests\Agent\Analytics\AnalyticsDateRangeRequest;
use Domain\Agent\Actions\Analytics\BuildAgentStatsAction;
use Domain\Agent\Data\Resources\Stats\StatsResourceData;
use Domain\Agent\Models\Agent;

/**
 * Headline numbers for the agent analytics overview over a date range.
 */
class AgentAnalyticsDataController
{
    public function __invoke(AnalyticsDateRangeRequest $request, Agent $agent, BuildAgentStatsAction $build): StatsResourceData
    {
        return $build->execute($agent, $request->toData());
    }
}

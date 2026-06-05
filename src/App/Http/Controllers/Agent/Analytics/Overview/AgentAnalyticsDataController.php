<?php

namespace App\Http\Controllers\Agent\Analytics\Overview;

use Domain\Agent\Data\Resources\Stats\StatsResourceData;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\DateRangeData;
use Illuminate\Http\Request;

class AgentAnalyticsDataController
{
    public function __invoke(Request $request, Agent $agent): StatsResourceData
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $range = DateRangeData::fromRequestOrLastDays($request);

        return StatsResourceData::fromAgent($agent, $range->start, $range->end);
    }
}

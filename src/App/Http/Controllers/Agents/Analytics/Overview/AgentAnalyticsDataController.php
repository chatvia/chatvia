<?php

namespace App\Http\Controllers\Agents\Analytics\Overview;

use Domain\Agents\Data\Resources\Stats\StatsResourceData;
use Domain\Agents\Models\Agent;
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

        $startDate = $request->input('start_date') ? now()->parse($request->input('start_date'))->startOfDay() : now()->subDays(6)->startOfDay();
        $endDate = $request->input('end_date') ? now()->parse($request->input('end_date'))->endOfDay() : now()->endOfDay();

        return StatsResourceData::fromAgent($agent, $startDate, $endDate);
    }
}

<?php

namespace App\Http\Controllers\Agents\Analytics;

use Domain\Agents\Data\Topics\TopicsResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AgentTopicsDataController
{
    public function __invoke(Request $request, Agent $agent): TopicsResourceData
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : null;

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : null;

        return TopicsResourceData::fromAgent($agent, $startDate, $endDate);
    }
}

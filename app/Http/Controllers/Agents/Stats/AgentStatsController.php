<?php

namespace App\Http\Controllers\Agents\Stats;

use App\Data\Agent\Stats\StatsData;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentStatsController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $analytics = StatsData::fromAgent($agent);

        return Inertia::render('agents/Stats', [
            'agent' => $agent,
            'analytics' => $analytics,
        ]);
    }
}

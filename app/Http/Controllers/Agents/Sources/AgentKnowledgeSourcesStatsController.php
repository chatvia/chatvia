<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Data\Resources\Agent\AgentSourcesStatsData;
use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeSourcesStatsController
{
    public function __invoke(Request $request, Agent $agent): AgentSourcesStatsData
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return AgentSourcesStatsData::fromAgent($agent);
    }
}

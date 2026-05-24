<?php

namespace App\Http\Controllers\Agents\Sources;

use Domain\Agents\Data\Resources\AgentSourcesStatsData;
use Domain\Agents\Models\Agent;
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

<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentKnowledgeYoutubeController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Sources/Youtube', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}

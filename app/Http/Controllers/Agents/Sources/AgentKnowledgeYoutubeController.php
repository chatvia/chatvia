<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentKnowledgeYoutubeController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Sources/Youtube', [
            'agent' => $agent,
        ]);
    }
}

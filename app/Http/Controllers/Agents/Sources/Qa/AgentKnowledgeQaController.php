<?php

namespace App\Http\Controllers\Agents\Sources\Qa;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentKnowledgeQaController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Sources/Qa', [
            'agent' => $agent,
        ]);
    }
}

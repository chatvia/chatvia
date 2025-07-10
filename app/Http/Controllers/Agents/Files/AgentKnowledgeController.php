<?php

namespace App\Http\Controllers\Agents\Files;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentKnowledgeController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Knowledge', ['agent' => $agent]);
    }
}

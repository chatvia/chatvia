<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentKnowledgeTextController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Sources/Text', [
            'agent' => $agent,
        ]);
    }
}

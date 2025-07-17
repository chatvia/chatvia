<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentTeamController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Team', [
            'agent' => $agent,
        ]);
    }
}

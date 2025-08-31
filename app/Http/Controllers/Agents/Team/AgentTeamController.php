<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentTeamController
{
    public function __invoke(Request $request, Agent $agent)
    {
        if (! $agent->isKnowledgeable()) {
            return back()->with('error', 'Agent is not a knowledge base!');
        }

        $user = $request->user();

        abort_if(
            boolean: $user->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/Team', [
            'agent' => $agent,
            'team_collaboration' => $user->capabilityAllows('team_collaboration'),
            'members_limit' => $user->members_limit,
        ]);
    }
}

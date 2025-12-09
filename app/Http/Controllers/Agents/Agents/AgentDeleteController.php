<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentDeleteController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $user = $request->user();
        $team = current_team();

        // Only team owners can delete agents
        if (! $team || ! $user->ownsTeam($team)) {
            abort(403, 'Only team owners can delete agents.');
        }

        // Verify agent belongs to this team
        if ($agent->team_id !== $team->id) {
            abort(404);
        }

        $agent->delete();

        return redirect()->route('agents.index');
    }
}

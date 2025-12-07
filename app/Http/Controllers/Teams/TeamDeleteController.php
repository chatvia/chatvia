<?php

namespace App\Http\Controllers\Teams;

use App\Models\Team;
use Illuminate\Http\Request;

class TeamDeleteController
{
    public function __invoke(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            return back()->withErrors(['team' => 'Only the team owner can delete the team.']);
        }

        if ($user->isPrimaryTeam($team)) {
            return back()->withErrors(['team' => 'You cannot delete your primary team.']);
        }

        if ($user->ownedTeams()->count() <= 1) {
            return back()->withErrors(['team' => 'You cannot delete your only team. Create another team first.']);
        }

        if ($team->agents()->exists()) {
            return back()->withErrors(['team' => 'You cannot delete a team that has agents. Please delete or move all agents first.']);
        }

        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Team deleted successfully.');
    }
}

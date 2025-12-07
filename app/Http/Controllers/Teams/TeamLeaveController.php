<?php

namespace App\Http\Controllers\Teams;

use App\Models\Team;
use Illuminate\Http\Request;

class TeamLeaveController
{
    public function __invoke(Request $request, Team $team)
    {
        $user = $request->user();

        if ($user->ownsTeam($team)) {
            return back()->withErrors(['team' => 'Team owners cannot leave their own team. Transfer ownership first or delete the team.']);
        }

        $team->deleteUser($user);

        return redirect()->route('teams.index')->with('success', 'You have left the team.');
    }
}

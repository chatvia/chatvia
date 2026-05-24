<?php

namespace App\Http\Controllers\Teams;

use App\Teams\Models\Team;
use Illuminate\Http\Request;

class TeamSwitchController
{
    public function __invoke(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->switchTeam($team)) {
            abort(403, 'You do not have access to this team.');
        }

        return redirect()->back()->with('success', 'Switched to '.$team->name);
    }
}

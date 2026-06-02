<?php

namespace App\Http\Controllers\Team\Switching;

use App\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamSwitchController
{
    public function __invoke(Request $request, Team $team): RedirectResponse
    {
        if (! $request->user()->switchTeam($team)) {
            abort(403, 'You do not have access to this team.');
        }

        return redirect()->back()->with('success', 'Switched to '.$team->name);
    }
}

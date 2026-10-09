<?php

namespace App\Http\Controllers\Team\Switching;

use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Makes another team the user's current team.
 */
class TeamSwitchController
{
    public function __invoke(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->switchTeam($team), 403, __('You do not have access to this team.'));

        return redirect()->back()->with('success', __('Switched to :team', ['team' => $team->name]));
    }
}

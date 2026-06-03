<?php

namespace App\Http\Controllers\Team\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\LeaveTeamAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamLeaveController
{
    public function __invoke(Request $request, Team $team, LeaveTeamAction $leave): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->ownsTeam($team)) {
            return back()->withErrors(['team' => 'Team owners cannot leave their own team. Transfer ownership first or delete the team.']);
        }

        $leave->execute($team, $user);

        return redirect()->route('teams.index')->with('success', 'You have left the team.');
    }
}

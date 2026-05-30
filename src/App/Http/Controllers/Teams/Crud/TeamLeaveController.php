<?php

namespace App\Http\Controllers\Teams\Crud;

use App\Auth\Models\User;
use App\Teams\Models\Team;
use Domain\Teams\Actions\LeaveTeamAction;
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

<?php

namespace App\Http\Controllers\Team\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets a teammate leave a team. The owner cannot leave their own team.
 */
class TeamLeaveController
{
    public function __invoke(Request $request, Team $team): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->ownsTeam($team)) {
            return back()->withErrors(['team' => __('Team owners cannot leave their own team. Transfer ownership first or delete the team.')]);
        }

        $team->removeMember($user);

        return redirect()->route('teams.index')->with('success', __('You have left the team.'));
    }
}

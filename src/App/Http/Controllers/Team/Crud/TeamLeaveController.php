<?php

namespace App\Http\Controllers\Team\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\RemoveTeamMemberAction;
use Domain\Membership\Team\Exceptions\TeamOwnerImmutableException;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets a teammate leave a team. The owner cannot leave their own team — the
 * Action refuses, and the refusal is shown on the form rather than as a 403.
 */
class TeamLeaveController
{
    public function __invoke(Request $request, Team $team, RemoveTeamMemberAction $remove): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $remove->execute($team, $user);
        } catch (TeamOwnerImmutableException) {
            return back()->withErrors(['team' => __('Team owners cannot leave their own team. Transfer ownership first or delete the team.')]);
        }

        return redirect()->route('teams.index')->with('success', __('You have left the team.'));
    }
}

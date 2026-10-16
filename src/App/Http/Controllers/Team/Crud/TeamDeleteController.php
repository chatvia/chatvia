<?php

namespace App\Http\Controllers\Team\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\DeleteTeamAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Deletes a team. Only the owner may, and never their primary or only team,
 * nor a team that still has agents (DeleteTeamAction holds those guards,
 * shared with the public API; a refusal redirects back with the field error).
 */
class TeamDeleteController
{
    public function __invoke(Request $request, Team $team, DeleteTeamAction $deleteTeam): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->cannot('delete', $team)) {
            return back()->withErrors(['team' => __('Only the team owner can delete the team.')]);
        }

        $deleteTeam->execute($user, $team);

        return redirect()->route('teams.index')->with('success', __('Team deleted successfully.'));
    }
}

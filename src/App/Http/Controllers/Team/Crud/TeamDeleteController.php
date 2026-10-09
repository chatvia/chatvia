<?php

namespace App\Http\Controllers\Team\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Deletes a team. Only the owner may, and never their primary or only team,
 * nor a team that still has agents.
 */
class TeamDeleteController
{
    public function __invoke(Request $request, Team $team): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->cannot('delete', $team)) {
            return back()->withErrors(['team' => __('Only the team owner can delete the team.')]);
        }

        if ($user->isPrimaryTeam($team)) {
            return back()->withErrors(['team' => __('You cannot delete your primary team.')]);
        }

        if ($user->ownedTeams()->count() <= 1) {
            return back()->withErrors(['team' => __('You cannot delete your only team. Create another team first.')]);
        }

        if ($team->agents()->exists()) {
            return back()->withErrors(['team' => __('You cannot delete a team that has agents. Please delete or move all agents first.')]);
        }

        // Trivial single-call state change — inlined per the "trivial writes
        // don't need an Action" rule; the guard rules above are the real logic.
        $team->delete();

        return redirect()->route('teams.index')->with('success', __('Team deleted successfully.'));
    }
}

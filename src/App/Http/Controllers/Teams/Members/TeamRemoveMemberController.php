<?php

namespace App\Http\Controllers\Teams\Members;

use App\Auth\Models\User;
use App\Teams\Models\Team;
use Domain\Teams\Actions\Members\RemoveTeamMemberAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamRemoveMemberController
{
    public function __invoke(
        Request $request,
        Team $team,
        User $member,
        RemoveTeamMemberAction $remove,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can remove members.');
        }

        if ($team->user_id === $member->id) {
            abort(403, 'Cannot remove the team owner.');
        }

        $remove->execute($team, $member);

        return back()->with('success', 'Member removed from team.');
    }
}

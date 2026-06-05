<?php

namespace App\Http\Controllers\Team\Member;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamRemoveMemberController
{
    public function __invoke(Request $request, Team $team, User $member): RedirectResponse
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can remove members.');
        }

        if ($team->user_id === $member->id) {
            abort(403, 'Cannot remove the team owner.');
        }

        $team->removeMember($member);

        return back()->with('success', 'Member removed from team.');
    }
}

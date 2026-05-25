<?php

namespace App\Http\Controllers\Teams;

use App\Auth\Models\User;
use App\Teams\Models\Team;
use Illuminate\Http\Request;

class TeamRemoveMemberController
{
    public function __invoke(Request $request, Team $team, User $member)
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

<?php

namespace App\Http\Controllers\Team\Member;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\RemoveTeamMemberAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Removes a member from a team (detaches the pivot; the account stays). Only
 * the owner may, and the owner can never be removed (the Action refuses).
 */
class TeamRemoveMemberController
{
    public function __invoke(Request $request, Team $team, User $member, RemoveTeamMemberAction $remove): RedirectResponse
    {
        abort_if($request->user()->cannot('removeTeamMember', $team), 403, __('Only the team owner can remove members.'));

        $remove->execute($team, $member);

        return back()->with('success', __('Member removed from team.'));
    }
}

<?php

namespace App\Http\Controllers\Team\Member;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Removes a member from a team (detaches the pivot; the account stays). Only
 * the owner may, and the owner can never be removed.
 */
class TeamRemoveMemberController
{
    public function __invoke(Request $request, Team $team, User $member): RedirectResponse
    {
        abort_if($request->user()->cannot('removeTeamMember', $team), 403, __('Only the team owner can remove members.'));
        abort_if($member->ownsTeam($team), 403, __('Cannot remove the team owner.'));

        $team->removeMember($member);

        return back()->with('success', __('Member removed from team.'));
    }
}

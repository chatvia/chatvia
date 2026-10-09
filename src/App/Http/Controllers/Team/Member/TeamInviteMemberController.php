<?php

namespace App\Http\Controllers\Team\Member;

use App\Http\Requests\Team\InviteTeamMemberRequest;
use Domain\Membership\Team\Actions\Members\InviteTeamMemberAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;

/**
 * Adds a managed user to a team, or emails an invitation to a new address.
 */
class TeamInviteMemberController
{
    public function __invoke(
        InviteTeamMemberRequest $request,
        Team $team,
        InviteTeamMemberAction $invite,
    ): RedirectResponse {
        $invite->execute($request->toData($team));

        return back()->with('success', __('Invitation sent successfully.'));
    }
}

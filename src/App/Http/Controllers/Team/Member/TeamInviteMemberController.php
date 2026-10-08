<?php

namespace App\Http\Controllers\Team\Member;

use App\Http\Requests\Team\InviteTeamMemberRequest;
use Domain\Membership\Team\Actions\Members\InviteTeamMemberAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;

class TeamInviteMemberController
{
    public function __invoke(
        InviteTeamMemberRequest $request,
        Team $team,
        InviteTeamMemberAction $invite,
    ): RedirectResponse {
        $invite->execute($request->toData($team));

        return back()->with('success', 'Invitation sent successfully.');
    }
}

<?php

namespace App\Http\Controllers\Team\Member;

use App\Http\Requests\Team\InviteTeamMemberRequest;
use App\Team\Models\Team;
use Domain\Team\Actions\Members\InviteTeamMemberAction;
use Domain\Team\Exceptions\InviteRejectedException;
use Illuminate\Http\RedirectResponse;

class TeamInviteMemberController
{
    public function __invoke(
        InviteTeamMemberRequest $request,
        Team $team,
        InviteTeamMemberAction $invite,
    ): RedirectResponse {
        try {
            $invite->execute($request->toData($team));
        } catch (InviteRejectedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', 'Invitation sent successfully.');
    }
}

<?php

namespace App\Http\Controllers\Teams\Members;

use App\Http\Requests\Teams\InviteTeamMemberRequest;
use App\Teams\Models\Team;
use Domain\Teams\Actions\Members\InviteTeamMemberAction;
use Domain\Teams\Exceptions\InviteRejectedException;
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

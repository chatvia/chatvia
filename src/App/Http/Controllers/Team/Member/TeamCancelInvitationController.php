<?php

namespace App\Http\Controllers\Team\Member;

use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamCancelInvitationController
{
    public function __invoke(
        Request $request,
        Team $team,
        int $invitationId,
    ): RedirectResponse {
        $user = $request->user();

        if ($user->cannot('removeTeamMember', $team)) {
            abort(403, 'Only the team owner can cancel invitations.');
        }

        $team->invitations()->findOrFail($invitationId)->delete();

        return back()->with('success', 'Invitation cancelled.');
    }
}

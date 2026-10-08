<?php

namespace App\Http\Controllers\Team\Member;

use Domain\Membership\Team\Actions\Invitations\ResendTeamInvitationAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamResendInvitationController
{
    public function __invoke(
        Request $request,
        Team $team,
        int $invitationId,
        ResendTeamInvitationAction $resend,
    ): RedirectResponse {
        $user = $request->user();

        if ($user->cannot('addTeamMember', $team)) {
            abort(403, 'You are not allowed to resend invitations for this team.');
        }

        $invitation = $team->invitations()->findOrFail($invitationId);

        $resend->execute($invitation);

        return back()->with('success', 'Invitation resent.');
    }
}

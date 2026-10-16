<?php

namespace App\Http\Controllers\Team\Member;

use Domain\Membership\Team\Actions\Invitations\ResendTeamInvitationAction;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Re-sends a pending team invitation's email.
 */
class TeamResendInvitationController
{
    public function __invoke(
        Request $request,
        Team $team,
        Invitation $invitation,
        ResendTeamInvitationAction $resend,
    ): RedirectResponse {
        abort_if($request->user()->cannot('addTeamMember', $team), 403, __('You are not allowed to resend invitations for this team.'));

        $resend->execute($invitation);

        return back()->with('success', __('Invitation resent.'));
    }
}

<?php

namespace App\Http\Controllers\Team\Member;

use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cancels a pending team invitation. Only the owner may.
 */
class TeamCancelInvitationController
{
    public function __invoke(
        Request $request,
        Team $team,
        Invitation $invitation,
    ): RedirectResponse {
        abort_if($request->user()->cannot('removeTeamMember', $team), 403, __('Only the team owner can cancel invitations.'));

        $invitation->delete();

        return back()->with('success', __('Invitation cancelled.'));
    }
}

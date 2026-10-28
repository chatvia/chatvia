<?php

namespace App\Http\Controllers\Team\Invitations;

use App\Http\Resources\Invitations\TeamInvitationPageProps;
use Domain\Membership\Team\Models\Invitation;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Show the landing page for a team invitation. Accepting the invitation runs
 * through {@see AcceptTeamInvitationController}.
 */
class TeamInvitationController
{
    public function show(TeamInvitationPageProps $props, Invitation $invitation): Response
    {
        if ($invitation->hasExpired()) {
            return Inertia::render('auth/pages/InvitationExpired', [
                'message' => __('This invitation has expired.'),
            ]);
        }

        return Inertia::render('auth/pages/AcceptInvitation', $props->for($invitation));
    }
}

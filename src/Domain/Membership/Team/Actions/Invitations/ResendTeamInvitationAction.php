<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Actions\Invitations;

use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Listeners\SendTeamInvitationEmail;
use Domain\Membership\Team\Models\Invitation;

/**
 * Re-sends an existing pending invitation's email.
 *
 * Mirrors the tail of {@see CreateTeamInvitationAction}: it refreshes the
 * "Sent" timestamp (which also re-arms the {@see Invitation::TTL_DAYS}-day
 * acceptance window enforced in {@see AcceptTeamInvitationAction}), then fires
 * {@see TeamInvitationCreated} so the mail goes out through
 * {@see SendTeamInvitationEmail} — the same listener the initial invite uses.
 * The signed accept link is derived from the invitation id, so resending
 * produces a valid link without minting a new row.
 */
class ResendTeamInvitationAction
{
    public function execute(Invitation $invitation): void
    {
        $invitation->forceFill(['created_at' => now()])->save();

        TeamInvitationCreated::dispatch($invitation, $invitation->email);
    }
}

<?php

namespace Domain\Membership\Team\Actions\Invitations;

use Domain\Membership\Team\Actions\Members\InviteTeamMemberAction;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Listeners\SendTeamInvitationEmail;
use Domain\Membership\Team\Models\Invitation;
use Support\Exceptions\DomainValidationException;

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
 *
 * Resending gates on the SAME active-subscription requirement the initial
 * invite ({@see InviteTeamMemberAction}) and the accept step both enforce —
 * emailing an invite that could never be accepted is pointless. The seat-limit
 * gate is deliberately NOT re-checked here: a pending invitation reserves no
 * seat (see InteractsWithWorkspaceQuotas::workspaceUsersCount), so the seat is
 * consumed and validated only at create and accept time.
 */
class ResendTeamInvitationAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Invitation $invitation): void
    {
        if (! $invitation->team->canInviteMembers()) {
            throw new DomainValidationException('invitation', __('You need an active subscription to resend invitations.'));
        }

        $invitation->forceFill(['created_at' => now()])->save();

        TeamInvitationCreated::dispatch($invitation, $invitation->email);
    }
}

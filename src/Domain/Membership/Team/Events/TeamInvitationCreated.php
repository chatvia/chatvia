<?php

namespace Domain\Membership\Team\Events;

use Domain\Membership\Team\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when CreateTeamInvitationAction persists an invitation row. The
 * mail send lives in {@see SendTeamInvitationEmail}, so every notification
 * goes through a listener.
 */
class TeamInvitationCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $email,
    ) {}
}

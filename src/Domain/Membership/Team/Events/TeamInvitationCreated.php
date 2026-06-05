<?php

namespace Domain\Membership\Team\Events;

use Domain\Membership\Team\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an invitation row is persisted by TeamService::inviteUser.
 * The mail send lives in {@see SendTeamInvitationEmail} so the inline
 * Notification::route call no longer sits in the service body — matches
 * the codebase convention that all notifications go through listeners.
 */
class TeamInvitationCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $email,
    ) {}
}

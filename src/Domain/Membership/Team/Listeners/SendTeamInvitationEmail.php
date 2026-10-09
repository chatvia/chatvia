<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Listeners;

use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Emails the invitation link for {@see TeamInvitationCreated}, routed straight
 * to the invited address (the invitee may not have an account yet).
 */
class SendTeamInvitationEmail
{
    public function handle(TeamInvitationCreated $event): void
    {
        Notification::route('mail', $event->email)
            ->notify(new TeamInvitationNotification($event->invitation));
    }
}

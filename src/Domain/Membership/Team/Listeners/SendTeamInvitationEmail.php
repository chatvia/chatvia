<?php

namespace Domain\Membership\Team\Listeners;

use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;

class SendTeamInvitationEmail
{
    public function handle(TeamInvitationCreated $event): void
    {
        Notification::route('mail', $event->email)
            ->notify(new TeamInvitationNotification($event->invitation));
    }
}

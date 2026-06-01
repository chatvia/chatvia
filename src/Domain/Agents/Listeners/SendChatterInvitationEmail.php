<?php

namespace Domain\Agents\Listeners;

use Domain\Agents\Events\AgentMemberInvited;
use Domain\Agents\Notifications\ChatterInvitationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Listens for AgentMemberInvited and emails the recipient. Uses on-demand
 * routing (Notification::route('mail', ...)) because the invitee may not yet
 * have a User row at the time the invitation is created.
 */
class SendChatterInvitationEmail
{
    public function handle(AgentMemberInvited $event): void
    {
        Notification::route('mail', $event->email)
            ->notify(new ChatterInvitationNotification($event->agent, $event->acceptUrl));
    }
}

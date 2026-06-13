<?php

namespace Domain\Membership\Agent\Listeners;

use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Notifications\AgentChatterInvitationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Listens for AgentChatterInvited and emails the recipient. Uses on-demand
 * routing (Notification::route('mail', ...)) because the invitee may not yet
 * have a User row at the time the invitation is created.
 */
class SendAgentChatterInvitationEmail
{
    public function handle(AgentChatterInvited $event): void
    {
        Notification::route('mail', $event->email)
            ->notify(new AgentChatterInvitationNotification($event->agent, $event->acceptUrl));
    }
}

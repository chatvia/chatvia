<?php

namespace Domain\Member\Listeners;

use Domain\Member\Events\AgentMemberInvited;
use Domain\Member\Notifications\AgentMemberInvitationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Listens for AgentMemberInvited and emails the recipient. Uses on-demand
 * routing (Notification::route('mail', ...)) because the invitee may not yet
 * have a User row at the time the invitation is created.
 */
class SendAgentMemberInvitationEmail
{
    public function handle(AgentMemberInvited $event): void
    {
        Notification::route('mail', $event->email)
            ->notify(new AgentMemberInvitationNotification($event->agent, $event->acceptUrl));
    }
}

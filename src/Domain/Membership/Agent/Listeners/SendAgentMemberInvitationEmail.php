<?php

namespace Domain\Membership\Agent\Listeners;

use Domain\Membership\Agent\Events\AgentMemberInvited;
use Domain\Membership\Agent\Notifications\AgentMemberInvitationNotification;
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

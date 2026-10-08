<?php

namespace Domain\Membership\Agent\Listeners;

use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Notifications\AgentChatterInvitationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Emails the invitation link for {@see AgentChatterInvited}. Queued and run
 * only after the inviting transaction commits: the event fires inside that
 * transaction, and if a later step rolls it back the token no longer exists,
 * so the email must not go out. Uses on-demand mail routing so it does not
 * depend on the chatter's User row.
 */
class SendAgentChatterInvitationEmail implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(AgentChatterInvited $event): void
    {
        Notification::route('mail', $event->email)
            ->notify(new AgentChatterInvitationNotification($event->agent, $event->token));
    }
}

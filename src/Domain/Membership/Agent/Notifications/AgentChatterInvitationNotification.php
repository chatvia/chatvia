<?php

namespace Domain\Membership\Agent\Notifications;

use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email sent to someone invited to chat with a specific agent. The link carries
 * the random token of their `agent_chatter_invitations` row (valid for
 * {@see AgentChatterInvitation::TTL_DAYS} days, single use); opening it lands
 * the recipient on the chatter-onboarding page where they set a password or
 * sign in with a social provider.
 */
class AgentChatterInvitationNotification extends Notification
{
    public function __construct(
        public Agent $agent,
        public string $token,
    ) {}

    public function acceptUrl(): string
    {
        return route('agents.invitation.show', ['invitation' => $this->token]);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__("You've been invited to chat with :name", ['name' => $this->agent->name]))
            ->greeting(__('Hello!'))
            ->line(__("You've been invited to chat with **:name**.", ['name' => $this->agent->name]))
            ->line(__('Click the button below to accept and set up your access.'))
            ->action(__('Accept invitation'), $this->acceptUrl())
            ->line(__('This invitation will expire in :days days.', ['days' => AgentChatterInvitation::TTL_DAYS]))
            ->line(__("If you didn't expect this invitation, you can ignore this email."));
    }
}

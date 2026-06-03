<?php

namespace Domain\Membership\Agent\Notifications;

use Domain\Agent\Models\Agent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email sent to someone invited to chat with a specific agent. The accept URL
 * is a pre-signed, time-limited link (no DB row) — opening it lands the
 * recipient on the chatter-onboarding page where they set a password or sign
 * in with a social provider.
 */
class AgentMemberInvitationNotification extends Notification
{
    public function __construct(
        public Agent $agent,
        public string $acceptUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You've been invited to chat with {$this->agent->name}")
            ->greeting('Hello!')
            ->line("You've been invited to chat with **{$this->agent->name}**.")
            ->line('Click the button below to accept and set up your access.')
            ->action('Accept invitation', $this->acceptUrl)
            ->line('This invitation will expire in 7 days.')
            ->line("If you didn't expect this invitation, you can ignore this email.");
    }
}

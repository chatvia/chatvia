<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TeamInvitationNotification extends Notification
{
    public function __construct(
        public Invitation $invitation
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $owner = $team->owner;
        $role = $this->invitation->role;

        $acceptUrl = URL::signedRoute('teams.invitation.show', [
            'invitation' => $this->invitation->id,
        ]);

        return (new MailMessage)
            ->subject("You've been invited to join {$team->name}")
            ->greeting('Hello!')
            ->line("{$owner->name} has invited you to join their team **{$team->name}** as a **{$role->name}**.")
            ->line('Click the button below to accept the invitation and create your account.')
            ->action('Accept Invitation', $acceptUrl)
            ->line('This invitation will expire in 7 days.')
            ->line("If you didn't expect this invitation, you can ignore this email.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'team_id' => $this->invitation->team_id,
            'team_name' => $this->invitation->team->name,
        ];
    }
}

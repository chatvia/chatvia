<?php

namespace Domain\Membership\Team\Notifications;

use Domain\Membership\Team\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Email that invites someone to join a team under a role. The accept button is
 * a signed link to the invitation page; the link stops working after
 * {@see Invitation::TTL_DAYS} days.
 */
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
            ->subject(__("You've been invited to join :team", ['team' => $team->name]))
            ->greeting(__('Hello!'))
            ->line(__(':owner has invited you to join their team **:team** as a **:role**.', ['owner' => $owner->name, 'team' => $team->name, 'role' => $role->name]))
            ->line(__('Click the button below to accept the invitation and create your account.'))
            ->action(__('Accept Invitation'), $acceptUrl)
            ->line(__('This invitation will expire in :days days.', ['days' => Invitation::TTL_DAYS]))
            ->line(__("If you didn't expect this invitation, you can ignore this email."));
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

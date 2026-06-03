<?php

namespace App\Identity\Workspace\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Heads-up email sent the first time we see a user sign in from a given
 * (IP, User-Agent) combination. The link points at /settings/password so the
 * recipient can lock the account down if it wasn't them.
 */
class NewDeviceSignInNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $ip,
        public ?string $userAgent,
        public Carbon $signedInAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New sign-in to your account')
            ->greeting('Hello!')
            ->line('We noticed a sign-in to your account from a device we haven\'t seen before.')
            ->line('**When:** '.$this->signedInAt->toDayDateTimeString().' UTC')
            ->line('**IP address:** '.($this->ip ?? 'unknown'))
            ->line('**Browser / device:** '.($this->userAgent ?? 'unknown'))
            ->line('If this was you, no action is needed.')
            ->action('Review your password', url(route('settings.password.edit', absolute: true)))
            ->line('If this wasn\'t you, change your password immediately and review your two-factor settings.');
    }
}

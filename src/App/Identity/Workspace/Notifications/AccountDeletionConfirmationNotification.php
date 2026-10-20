<?php

namespace App\Identity\Workspace\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Second factor for account deletion. The recipient already confirmed their
 * password; opening this link returns them to the Delete Account page in a
 * confirmed state, where a final click permanently deletes the account. The
 * link is a temporary signed URL that expires in 30 minutes.
 */
class AccountDeletionConfirmationNotification extends Notification
{
    public function __construct(
        public string $url,
        public string $name,
    ) {}

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
            ->subject(__('Confirm your account deletion'))
            ->greeting(__('Hi :name,', ['name' => $this->name]))
            ->line(__('We received a request to permanently delete your :app account.', ['app' => 'ChatVia.ai']))
            ->line(__('Click the button below to confirm. Nothing is deleted until you confirm again on the page that opens.'))
            ->action(__('Confirm account deletion'), $this->url)
            ->line(__('This link will expire in 30 minutes.'))
            ->line(__("If you didn't request this, you can safely ignore this email — your account stays active. We'd also recommend changing your password."));
    }
}

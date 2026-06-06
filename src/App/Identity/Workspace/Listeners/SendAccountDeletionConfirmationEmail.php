<?php

namespace App\Identity\Workspace\Listeners;

use App\Identity\Workspace\Events\AccountDeletionRequested;
use App\Identity\Workspace\Notifications\AccountDeletionConfirmationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Listens for AccountDeletionRequested and emails the signed confirmation link
 * to the account owner. Uses on-demand routing so the notification carries no
 * relationship to the (about-to-be-deleted) User model.
 */
class SendAccountDeletionConfirmationEmail
{
    public function handle(AccountDeletionRequested $event): void
    {
        Notification::route('mail', $event->email)
            ->notify(new AccountDeletionConfirmationNotification(
                url: $event->url,
                name: $event->name,
            ));
    }
}

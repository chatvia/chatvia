<?php

namespace App\Workspace\Listeners;

use App\Workspace\Events\MagicLinkRequested;
use App\Workspace\Notifications\MagicLinkNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Listens for MagicLinkRequested and emails the magic-link URL to the
 * recipient. Uses on-demand routing because the magic link is sometimes
 * issued without authenticating the User model first (only the email).
 */
class SendMagicLinkEmail
{
    public function handle(MagicLinkRequested $event): void
    {
        Notification::route('mail', $event->email)
            ->notify(new MagicLinkNotification(
                url: $event->url,
                contextName: $event->contextName,
            ));
    }
}

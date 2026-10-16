<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Listeners;

use App\Identity\Chatter\Enums\ChatterBlockNotice;
use App\Identity\Chatter\Events\ChatterBlocked;
use App\Identity\Chatter\Notifications\ChatterBlockedNotification;

/**
 * Tells a blocked chatter by email that their access was turned off, when the
 * admin chose to. The email is queued, in the chatter's own language.
 */
class SendChatterBlockedEmail
{
    public function handle(ChatterBlocked $event): void
    {
        $chatter = $event->chatter;

        if ($event->notice !== ChatterBlockNotice::Email || blank($chatter->email)) {
            return;
        }

        $chatter->notify(new ChatterBlockedNotification((string) $chatter->workspace?->name));
    }
}

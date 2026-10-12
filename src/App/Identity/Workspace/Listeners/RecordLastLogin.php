<?php

namespace App\Identity\Workspace\Listeners;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Stamps `last_login_at` on every successful sign-in of a staff account or a
 * chatter, whatever the path (password, two-factor challenge, passkey, magic
 * link, social, sign-in token).
 * Listening to the guard's Login event means a password check that stops at
 * the 2FA challenge is not counted as a login.
 */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User && ! $event->user instanceof Chatter) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}

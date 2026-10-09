<?php

namespace App\Identity\Chatter\Listeners;

use App\Identity\Chatter\Events\ChatterPasswordResetRequested;
use Illuminate\Support\Facades\Password;

/**
 * Emails the chatter the standard "reset your password" link. Nobody picks a
 * password for the chatter, so no plaintext credential is ever handed out
 * through the dashboard.
 */
class SendChatterPasswordResetLink
{
    public function handle(ChatterPasswordResetRequested $event): void
    {
        Password::sendResetLink(['email' => $event->chatter->email]);
    }
}

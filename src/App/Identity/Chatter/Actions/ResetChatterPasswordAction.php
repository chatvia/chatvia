<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Events\ChatterPasswordResetRequested;
use App\Identity\Workspace\Models\User;

/**
 * Starts a password reset for a chatter: the chatter follows the standard reset
 * link to choose their own password, the same flow as "forgot password". Does
 * nothing for a token-only chatter without an email.
 */
class ResetChatterPasswordAction
{
    public function execute(User $chatter): void
    {
        if ($chatter->email === null) {
            return;
        }

        ChatterPasswordResetRequested::dispatch($chatter);
    }
}

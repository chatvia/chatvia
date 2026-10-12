<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Events\ChatterPasswordResetRequested;
use App\Identity\Chatter\Models\Chatter;

/**
 * Starts a password reset for a chatter: the chatter follows the emailed link
 * to their hosted chat's reset page to choose their own password. Does
 * nothing for a token-only chatter without an email.
 */
class ResetChatterPasswordAction
{
    public function execute(Chatter $chatter): void
    {
        if ($chatter->email === null) {
            return;
        }

        ChatterPasswordResetRequested::dispatch($chatter);
    }
}

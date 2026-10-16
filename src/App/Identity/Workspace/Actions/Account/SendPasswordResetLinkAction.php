<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use Illuminate\Support\Facades\Password;

/**
 * Emails a password-reset link without ever telling the caller whether the
 * address exists — Password::sendResetLink no-ops internally for unknown
 * emails, and the result is thrown away, so valid and unknown addresses are
 * indistinguishable from the outside.
 */
class SendPasswordResetLinkAction
{
    public function execute(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }
}

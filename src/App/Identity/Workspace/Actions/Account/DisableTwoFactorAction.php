<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;

/**
 * Turns off both two-factor sign-ins of an account: the dashboard's and the
 * admin panel's. The server's operator runs it for someone who lost their
 * authenticator app and their recovery codes. Passkeys are left alone: they
 * are a way to sign in, not a second step.
 */
class DisableTwoFactorAction
{
    public function execute(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ])->save();
    }
}

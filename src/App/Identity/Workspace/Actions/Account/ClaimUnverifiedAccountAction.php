<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Hands an account that was never verified to the person who just proved
 * they own its email (a social sign-in with that email, a magic link, a
 * password reset link).
 *
 * Anyone can register an address they do not own and set its password; the
 * account stays unverified, but that person keeps the password, and could
 * add a sign-in of their own (a social login, a passkey, two-factor). So
 * before the owner of the email gets in, all of that is dropped: the account
 * is marked verified, gets a random password (the owner sets their own with
 * "Forgot password"), loses its social links, passkeys, two-factor setup and
 * API keys, and its remember-me token is rotated. The new password also ends
 * every session that was open (AuthenticateSession).
 *
 * An account that is already verified is left as it is.
 */
class ClaimUnverifiedAccountAction
{
    public function execute(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $user->socials()->delete();
            $user->passkeys()->delete();
            $user->tokens()->delete();

            $user->forceFill([
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(64)),
                'remember_token' => Str::random(60),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ])->save();
        });
    }
}

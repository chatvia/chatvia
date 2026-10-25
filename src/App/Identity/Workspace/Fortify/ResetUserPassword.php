<?php

namespace App\Identity\Workspace\Fortify;

use App\Identity\Workspace\Actions\Account\ClaimUnverifiedAccountAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Fortify's password-reset step (the {@see ResetsUserPasswords} contract,
 * registered in FortifyServiceProvider). Fortify calls `reset()` once the
 * emailed reset token checks out, so this is a framework callback rather than
 * one of our Actions.
 *
 * Every other session of the account ends on its next request, on the
 * dashboard and in hosted chats: each keeps a hash of the password it signed
 * in with (AuthenticateSession). Fortify then rotates the remember-me token.
 * An account that was never verified is claimed first
 * ({@see ClaimUnverifiedAccountAction}): the reset link proved the email, so
 * whatever someone attached to the account before that stops working.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(
        private readonly ClaimUnverifiedAccountAction $claimUnverified,
    ) {}

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        $this->claimUnverified->execute($user);

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}

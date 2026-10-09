<?php

namespace App\Identity\Workspace\Fortify;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Fortify's password-reset step (the {@see ResetsUserPasswords} contract,
 * registered in FortifyServiceProvider). Fortify calls `reset()` once the
 * emailed reset token checks out, so this is a framework callback rather than
 * one of our Actions. A new password also fulfils the first-time password
 * setup that trial-checkout accounts owe ({@see PasswordSetup}).
 */
class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(
        private readonly PasswordSetup $passwordSetup,
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

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        // A reset password fulfils the first-time setup requirement for
        // trial-checkout accounts.
        $this->passwordSetup->clear($user);
    }
}

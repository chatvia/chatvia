<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Data\Account\UpdatePasswordData;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Saves a new password for an account: the user's own change, or an admin's
 * reset from the panel or the command line.
 *
 * Every other session of the account ends on its next request, on the
 * dashboard and in hosted chats alike: each session keeps a hash of the
 * password it signed in with (AuthenticateSession), and that no longer
 * matches. The session that made the change stores the new hash and stays
 * signed in. The remember-me token is rotated too, so no "remember me"
 * cookie signs anyone back in. API keys are left alone: they are managed on
 * their own page.
 */
class UpdatePasswordAction
{
    public function execute(UpdatePasswordData $data): void
    {
        $data->user->forceFill([
            'password' => Hash::make($data->password),
            'remember_token' => Str::random(60),
        ])->save();
    }
}

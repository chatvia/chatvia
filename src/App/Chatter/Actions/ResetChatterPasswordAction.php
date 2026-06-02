<?php

namespace App\Chatter\Actions;

use App\Workspace\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Trigger a password-reset email for a chat member. We don't pick a new
 * password ourselves — the chatter follows the standard reset link to set
 * their own, which keeps this flow consistent with normal "forgot password"
 * and avoids handing out plaintext credentials through the dashboard.
 */
class ResetChatterPasswordAction
{
    public function execute(User $chatter): void
    {
        if ($chatter->email === null) {
            return;
        }

        Password::sendResetLink(['email' => $chatter->email]);
    }
}

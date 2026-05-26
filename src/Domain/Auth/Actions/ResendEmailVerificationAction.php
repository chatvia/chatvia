<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;

class ResendEmailVerificationAction
{
    /**
     * Returns true if a notification was actually sent, false if the email
     * was already verified.
     */
    public function execute(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->sendEmailVerificationNotification();

        return true;
    }
}

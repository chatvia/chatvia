<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class VerifyEmailAction
{
    public function execute(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->markEmailAsVerified()) {
            /** @var MustVerifyEmail $verifiable */
            $verifiable = $user;
            event(new Verified($verifiable));

            return true;
        }

        return false;
    }
}

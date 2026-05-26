<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;

class LogoutUserAction
{
    /**
     * Side-effects on the user record only — the controller is responsible
     * for the HTTP-layer parts (Auth::logout, session invalidate, etc.).
     */
    public function execute(User $user): void
    {
        $user->setRememberToken('');
        $user->save();
    }
}

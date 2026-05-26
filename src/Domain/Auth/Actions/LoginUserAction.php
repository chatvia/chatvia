<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;
use Domain\Auth\Data\LoginData;
use Illuminate\Support\Facades\Auth;

class LoginUserAction
{
    public function execute(LoginData $data): ?User
    {
        if (! Auth::attempt(['email' => $data->email, 'password' => $data->password], $data->remember)) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        $user->update(['last_login_at' => now()]);

        return $user;
    }
}

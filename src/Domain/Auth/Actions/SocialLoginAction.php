<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class SocialLoginAction
{
    public function execute(string $driver): ?User
    {
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver($driver);
        $socialUser = $provider->stateless()->user();

        $driverId = $driver.'_id';

        $user = User::where($driverId, $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if (! $user) {
            return null;
        }

        if ($user->email && ! $user->{$driverId}) {
            $user->update([$driverId => $socialUser->getId()]);
        }

        return $user;
    }
}

<?php

namespace App\Auth\Actions;

use App\Auth\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class SocialLoginAction
{
    public function execute(string $driver)
    {
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver($driver);
        $socialUser = $provider->stateless()->user();

        $driverId = $driver.'_id';

        $user = User::where($driver.'_id', $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if (! $user) {
            return redirect(route('login'))->with(['error' => 'No account found!']);
        }

        if ($user->email && ! $user->$driverId) {

            $data = [
                $driver.'_id' => $socialUser->getId(),
            ];

            $user->update($data);
        }

        Auth::login($user);

        return redirect(route('dashboard'));
    }
}

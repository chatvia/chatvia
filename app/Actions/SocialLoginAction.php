<?php

namespace App\Actions;

use App\Models\User;
use App\Services\IP2Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginAction
{
    public function execute(string $driver, Request $request): void
    {
        /** @var \Laravel\Socialite\Contracts\User $socialUser */
        $socialUser = Socialite::driver($driver)->stateless()->user();

        $user = User::where($driver.'_id', $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if ($user?->email && ! $user?->google_id) {

            $data = [
                $driver.'_id' => $socialUser->id,
            ];

            $user->update($data);
        }

        if (! $user) {
            /** @var User $user */
            $user = User::create([
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(Str::random()),
                'email_verified_at' => now(),
                $driver.'_id' => $socialUser->getId(),
                'registered_ip' => $request->ip(),
                'country_code' => IP2Country::getCountryByIP($request->ip()),
            ]);
        }

        Auth::login($user);
    }
}

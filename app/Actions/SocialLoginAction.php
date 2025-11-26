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
    public function execute(string $driver)
    {
        /** @var \Laravel\Socialite\Contracts\User $socialUser */
        $socialUser = Socialite::driver($driver)->stateless()->user();

        $driverId =  $driver.'_id';

        $user = User::where($driver.'_id', $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if (!$user) {
            return redirect(route('login'))->with(['error' => 'No account found!']);
        }

        if ($user->email && ! $user->$driverId) {

            $data = [
                $driver.'_id' => $socialUser->id,
            ];

            $user->update($data);
        }

        Auth::login($user);

        return redirect(route('dashboard'));
    }
}

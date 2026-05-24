<?php

namespace App\Http\Controllers\Auth\Social;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

final class GoogleAuthController
{
    public function __invoke(Request $request)
    {
        return Socialite::driver('google')->stateless()->redirect();
    }
}

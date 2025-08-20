<?php

namespace App\Http\Controllers\Auth\Social;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController
{
    public function __invoke(Request $request)
    {
        return Socialite::driver('microsoft')->stateless()->redirect();
    }
}

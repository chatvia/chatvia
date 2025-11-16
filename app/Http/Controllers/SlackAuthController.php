<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SlackAuthController
{
    public function __invoke(Request $request)
    {
        return Socialite::driver('slack')->stateless()->redirect();
    }
}

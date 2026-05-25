<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class SlackAuthController
{
    public function __invoke(Request $request)
    {
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('slack');

        return $provider->stateless()->redirect();
    }
}

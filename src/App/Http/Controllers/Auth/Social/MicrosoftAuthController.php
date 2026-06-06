<?php

namespace App\Http\Controllers\Auth\Social;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class MicrosoftAuthController
{
    public function __invoke(Request $request)
    {
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('microsoft');

        return $provider->redirect();
    }
}

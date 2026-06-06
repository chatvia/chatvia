<?php

namespace App\Http\Controllers\Auth\Social;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

final class GithubAuthController
{
    public function __invoke(Request $request)
    {
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('github');

        return $provider->redirect();
    }
}

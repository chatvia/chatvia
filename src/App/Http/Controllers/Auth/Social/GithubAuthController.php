<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Social;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * Starts the GitHub OAuth round-trip (sign-in or account link).
 */
final class GithubAuthController
{
    public function __invoke()
    {
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('github');

        return $provider->redirect();
    }
}

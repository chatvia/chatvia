<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Social;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * Starts the Google OAuth round-trip (sign-in or account link).
 */
final class GoogleAuthController
{
    public function __invoke(): RedirectResponse
    {
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('google');

        return $provider->redirect();
    }
}

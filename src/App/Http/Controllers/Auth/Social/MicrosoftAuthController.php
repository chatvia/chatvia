<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Social;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * Starts the Microsoft OAuth round-trip (sign-in or account link).
 */
class MicrosoftAuthController
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('microsoft');

        return $provider->redirect();
    }
}

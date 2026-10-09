<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Social\Slack;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * Starts the Slack OAuth round-trip (sign-in or account link).
 */
class SlackAuthController
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('slack');

        return $provider->redirect();
    }
}

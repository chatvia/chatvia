<?php

namespace App\Http\Controllers\Auth\Social;

use App\Http\Auth\SocialOauthCallback;
use Illuminate\Http\RedirectResponse;
use Support\Enums\User\SocialProvider;

/**
 * Google's OAuth return URL; the shared SocialOauthCallback does the work.
 */
final class GoogleCallbackAuthController
{
    public function __invoke(SocialOauthCallback $callback): RedirectResponse
    {
        return $callback->handle(SocialProvider::Google);
    }
}

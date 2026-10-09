<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Social;

use App\Http\Auth\SocialOauthCallback;
use App\Identity\Workspace\Enums\SocialProvider;
use Illuminate\Http\RedirectResponse;

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

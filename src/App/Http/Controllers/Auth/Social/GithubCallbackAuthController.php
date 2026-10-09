<?php

namespace App\Http\Controllers\Auth\Social;

use App\Http\Auth\SocialOauthCallback;
use Illuminate\Http\RedirectResponse;
use Support\Enums\User\SocialProvider;

/**
 * GitHub's OAuth return URL; the shared SocialOauthCallback does the work.
 */
final class GithubCallbackAuthController
{
    public function __invoke(SocialOauthCallback $callback): RedirectResponse
    {
        return $callback->handle(SocialProvider::Github);
    }
}

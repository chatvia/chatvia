<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Social;

use App\Http\Auth\SocialOauthCallback;
use App\Identity\Workspace\Enums\SocialProvider;
use Illuminate\Http\RedirectResponse;

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

<?php

namespace App\Http\Controllers\Auth\Social;

use App\Workspace\Social\SocialOauthCallback;
use Illuminate\Http\RedirectResponse;
use Support\Enums\User\SocialProvider;

final class GoogleCallbackAuthController
{
    public function __invoke(SocialOauthCallback $callback): RedirectResponse
    {
        return $callback->handle(SocialProvider::Google);
    }
}

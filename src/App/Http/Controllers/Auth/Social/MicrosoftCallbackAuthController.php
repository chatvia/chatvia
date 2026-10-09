<?php

namespace App\Http\Controllers\Auth\Social;

use App\Http\Auth\SocialOauthCallback;
use Illuminate\Http\RedirectResponse;
use Support\Enums\User\SocialProvider;

/**
 * Microsoft's OAuth return URL; the shared SocialOauthCallback does the work.
 */
class MicrosoftCallbackAuthController
{
    public function __invoke(SocialOauthCallback $callback): RedirectResponse
    {
        return $callback->handle(SocialProvider::Microsoft);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Social;

use App\Http\Auth\SocialOauthCallback;
use App\Identity\Workspace\Enums\SocialProvider;
use Illuminate\Http\RedirectResponse;

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

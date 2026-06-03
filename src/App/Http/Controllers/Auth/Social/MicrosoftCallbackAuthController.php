<?php

namespace App\Http\Controllers\Auth\Social;

use App\Identity\Workspace\Social\SocialOauthCallback;
use Illuminate\Http\RedirectResponse;
use Support\Enums\User\SocialProvider;

class MicrosoftCallbackAuthController
{
    public function __invoke(SocialOauthCallback $callback): RedirectResponse
    {
        return $callback->handle(SocialProvider::Microsoft);
    }
}

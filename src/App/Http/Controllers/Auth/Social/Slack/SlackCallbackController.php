<?php

namespace App\Http\Controllers\Auth\Social\Slack;

use App\Auth\Social\SocialOauthCallback;
use Illuminate\Http\RedirectResponse;
use Support\Enums\SocialProvider;

class SlackCallbackController
{
    public function __invoke(SocialOauthCallback $callback): RedirectResponse
    {
        return $callback->handle(SocialProvider::Slack);
    }
}

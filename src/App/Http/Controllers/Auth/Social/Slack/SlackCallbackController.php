<?php

namespace App\Http\Controllers\Auth\Social\Slack;

use App\Http\Auth\SocialOauthCallback;
use Illuminate\Http\RedirectResponse;
use Support\Enums\User\SocialProvider;

/**
 * Slack's OAuth return URL; the shared SocialOauthCallback does the work.
 */
class SlackCallbackController
{
    public function __invoke(SocialOauthCallback $callback): RedirectResponse
    {
        return $callback->handle(SocialProvider::Slack);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Social\Slack;

use App\Http\Auth\SocialOauthCallback;
use App\Identity\Workspace\Enums\SocialProvider;
use Illuminate\Http\RedirectResponse;

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

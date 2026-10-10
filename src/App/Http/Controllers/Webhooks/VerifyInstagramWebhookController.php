<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Concerns\InteractsWithMetaWebhooks;
use Domain\Channel\Enums\MetaApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Meta's GET verify handshake for the Instagram Messaging webhook: echo
 * `hub.challenge` when the app-level verify token matches. The events
 * themselves arrive at {@see InstagramWebhookController}.
 */
class VerifyInstagramWebhookController
{
    use InteractsWithMetaWebhooks;

    public function __invoke(Request $request): Response
    {
        return $this->metaChallengeResponse($request, MetaApp::Instagram->webhookVerifyToken());
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Concerns\InteractsWithMetaWebhooks;
use Domain\Channel\Enums\MetaApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Meta's GET verify handshake for the Messenger Platform webhook: echo
 * `hub.challenge` when the app-level verify token matches. The events
 * themselves arrive at {@see MessengerWebhookController}.
 */
class VerifyMessengerWebhookController
{
    use InteractsWithMetaWebhooks;

    public function __invoke(Request $request): Response
    {
        return $this->metaChallengeResponse($request, MetaApp::Messenger->webhookVerifyToken());
    }
}

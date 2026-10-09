<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Concerns\InteractsWithMetaWebhooks;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Instagram\Actions\ReceiveInstagramMessagesAction;
use Domain\Channel\Instagram\Data\InstagramInboundMessageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives Instagram Messaging webhooks for Chatvia's single Meta app. With one
 * app serving every workspace there's one app-level Callback URL (no per-account
 * secret in the path): the GET handshake is verified against the app-level
 * verify token, POSTed events against the app secret (HMAC-SHA256 of the raw
 * body, `X-Hub-Signature-256`). Verified deliveries go to
 * {@see ReceiveInstagramMessagesAction}, which routes, de-duplicates and queues
 * each message. POST always replies 200 — repeated non-2xx makes Meta disable
 * the subscription, and an unknown/spoofed payload should be a silent no-op.
 */
class InstagramWebhookController
{
    use InteractsWithMetaWebhooks;

    public function verify(Request $request): Response
    {
        return $this->metaChallengeResponse($request, MetaApp::Instagram->webhookVerifyToken());
    }

    public function receive(Request $request, ReceiveInstagramMessagesAction $receive): JsonResponse
    {
        if ($this->isSignedByMeta($request, MetaApp::Instagram)) {
            $receive->execute(InstagramInboundMessageData::listFromWebhook($request->all()));
        }

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Concerns\InteractsWithMetaWebhooks;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Instagram\Actions\ReceiveInstagramMessagesAction;
use Domain\Channel\Instagram\Data\InstagramInboundMessageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Instagram Messaging webhooks for Chatvia's single Meta app. With one
 * app serving every workspace there's one app-level Callback URL (no
 * per-account secret in the path; the GET handshake is
 * {@see VerifyInstagramWebhookController}): POSTed events are verified against
 * the app secret (HMAC-SHA256 of the raw body, `X-Hub-Signature-256`). Verified
 * deliveries go to {@see ReceiveInstagramMessagesAction}, which routes,
 * de-duplicates and queues each message. It always replies 200 — repeated
 * non-2xx makes Meta disable the subscription, and an unknown/spoofed payload
 * should be a silent no-op.
 */
class InstagramWebhookController
{
    use InteractsWithMetaWebhooks;

    public function __invoke(Request $request, ReceiveInstagramMessagesAction $receive): JsonResponse
    {
        if ($this->isSignedByMeta($request, MetaApp::Instagram)) {
            $receive->execute(InstagramInboundMessageData::listFromWebhook($request->all()));
        }

        return response()->json(['ok' => true]);
    }
}

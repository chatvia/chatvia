<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Concerns\InteractsWithMetaWebhooks;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Messenger\Actions\ReceiveMessengerMessagesAction;
use Domain\Channel\Messenger\Data\MessengerInboundMessageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Messenger Platform webhooks for Chatvia's single Meta app. With one
 * app serving every workspace there's one app-level Callback URL (no per-page
 * secret in the path; the GET handshake is
 * {@see VerifyMessengerWebhookController}): POSTed events are verified against
 * the app secret (HMAC-SHA256 of the raw body, `X-Hub-Signature-256`). Verified
 * deliveries go to {@see ReceiveMessengerMessagesAction}, which routes,
 * de-duplicates and queues each message. It always replies 200 — repeated
 * non-2xx makes Meta disable the subscription, and an unknown/spoofed payload
 * should be a silent no-op.
 */
class MessengerWebhookController
{
    use InteractsWithMetaWebhooks;

    public function __invoke(Request $request, ReceiveMessengerMessagesAction $receive): JsonResponse
    {
        if ($this->isSignedByMeta($request, MetaApp::Messenger)) {
            $receive->execute(MessengerInboundMessageData::listFromWebhook($request->all()));
        }

        return response()->json(['ok' => true]);
    }
}

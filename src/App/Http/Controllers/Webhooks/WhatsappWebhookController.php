<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Concerns\InteractsWithMetaWebhooks;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Whatsapp\Actions\ReceiveWhatsappMessagesAction;
use Domain\Channel\Whatsapp\Data\WhatsappInboundMessageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives WhatsApp Business Cloud API webhooks for Chatvia's single Meta app.
 * With one app serving every workspace there's one app-level Callback URL (no
 * per-number secret in the path): the GET handshake is verified against the
 * app-level verify token, POSTed events against the app secret (HMAC-SHA256 of
 * the raw body, `X-Hub-Signature-256`). Verified deliveries go to
 * {@see ReceiveWhatsappMessagesAction}, which routes, de-duplicates and queues
 * each message. POST always replies 200 — repeated non-2xx makes Meta disable
 * the subscription, and an unknown/spoofed payload should be a silent no-op.
 */
class WhatsappWebhookController
{
    use InteractsWithMetaWebhooks;

    public function verify(Request $request): Response
    {
        return $this->metaChallengeResponse($request, MetaApp::Whatsapp->webhookVerifyToken());
    }

    public function receive(Request $request, ReceiveWhatsappMessagesAction $receive): JsonResponse
    {
        if ($this->isSignedByMeta($request, MetaApp::Whatsapp)) {
            $receive->execute(WhatsappInboundMessageData::listFromWebhook($request->all()));
        }

        return response()->json(['ok' => true]);
    }
}

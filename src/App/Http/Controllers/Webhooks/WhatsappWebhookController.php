<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Concerns\InteractsWithMetaWebhooks;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Whatsapp\Actions\ReceiveWhatsappMessagesAction;
use Domain\Channel\Whatsapp\Data\WhatsappInboundMessageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives WhatsApp Business Cloud API webhooks for Chatvia's single Meta app.
 * With one app serving every workspace there's one app-level Callback URL (no
 * per-number secret in the path; the GET handshake is
 * {@see VerifyWhatsappWebhookController}): POSTed events are verified against
 * the app secret (HMAC-SHA256 of the raw body, `X-Hub-Signature-256`). Verified
 * deliveries go to {@see ReceiveWhatsappMessagesAction}, which routes,
 * de-duplicates and queues each message. It always replies 200 — repeated
 * non-2xx makes Meta disable the subscription, and an unknown/spoofed payload
 * should be a silent no-op.
 */
class WhatsappWebhookController
{
    use InteractsWithMetaWebhooks;

    public function __invoke(Request $request, ReceiveWhatsappMessagesAction $receive): JsonResponse
    {
        if ($this->isSignedByMeta($request, MetaApp::Whatsapp)) {
            $receive->execute(WhatsappInboundMessageData::listFromWebhook($request->all()));
        }

        return response()->json(['ok' => true]);
    }
}

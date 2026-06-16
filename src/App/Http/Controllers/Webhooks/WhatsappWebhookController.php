<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Concerns\InteractsWithMetaWebhooks;
use Domain\Channel\Whatsapp\Jobs\HandleWhatsappMessageJob;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Receives WhatsApp Business Cloud API webhooks for Chatvia's single Meta app.
 * With one app serving every workspace there's one app-level Callback URL (no
 * per-number secret in the path): the GET handshake is verified against the
 * app-level verify token, POSTed events against the app secret (HMAC-SHA256 of
 * the raw body, `X-Hub-Signature-256`), and each change is routed to the right
 * agent by the phone number id that received the message
 * (`value.metadata.phone_number_id`). Each message is processed once
 * (idempotency on the wamid — Meta re-delivers for up to 7 days) and handed to
 * a queued job so the webhook answers fast. POST always replies 200 — repeated
 * non-2xx makes Meta disable the subscription, and an unknown/spoofed payload
 * should be a silent no-op.
 */
class WhatsappWebhookController
{
    use InteractsWithMetaWebhooks;

    public function verify(Request $request): Response
    {
        return $this->metaChallengeResponse($request, config('services.meta.webhook_verify_token'));
    }

    public function receive(Request $request): JsonResponse
    {
        $ok = response()->json(['ok' => true]);

        $appSecret = (string) config('services.meta.app_secret');

        if ($appSecret === '' || ! $this->hasValidMetaSignature($request, $appSecret) || $request->input('object') !== 'whatsapp_business_account') {
            return $ok;
        }

        // Events arrive batched: entry[] per WABA, changes[] per field,
        // messages[] per sender. One app-level URL receives every connected
        // number, so route each change to its agent by phone number id.
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (! is_array($change) || ($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];

                $number = $this->number($value['metadata']['phone_number_id'] ?? null);

                if ($number === null) {
                    continue;
                }

                $names = $this->contactNames($value);

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    if (! is_array($message) || ! $this->shouldHandle($message)) {
                        continue;
                    }

                    // Process each message at most once — Meta re-sends
                    // unacknowledged WhatsApp webhooks with decreasing
                    // frequency for up to 7 days, hence the matching 7-day TTL.
                    $mid = (string) $message['id'];
                    if (! Cache::add("wa:mid:{$number->id}:{$mid}", true, now()->addDays(7))) {
                        continue;
                    }

                    $waId = (string) $message['from'];

                    HandleWhatsappMessageJob::dispatch(
                        $number,
                        $waId,
                        (string) $message['text']['body'],
                        $mid,
                        $names[$waId] ?? null,
                    );
                }
            }
        }

        return $ok;
    }

    private function number(?string $phoneNumberId): ?AgentWhatsappNumber
    {
        if ($phoneNumberId === null || $phoneNumberId === '') {
            return null;
        }

        return AgentWhatsappNumber::query()
            ->with('agent')
            ->where('phone_number_id', $phoneNumberId)
            ->where('enabled', true)
            ->first();
    }

    /**
     * The sender's self-set profile name arrives alongside the messages —
     * map wa_id → name so the conversation gets a readable title.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, string>
     */
    private function contactNames(array $value): array
    {
        $names = [];

        foreach ((array) ($value['contacts'] ?? []) as $contact) {
            if (is_array($contact) && isset($contact['wa_id'], $contact['profile']['name'])) {
                $names[(string) $contact['wa_id']] = (string) $contact['profile']['name'];
            }
        }

        return $names;
    }

    /**
     * Answer plain text messages from users. Everything else — statuses
     * (delivery receipts for our own sends arrive under value.statuses, so
     * there is no echo loop), media, reactions, system events — is ignored.
     *
     * @param  array<string, mixed>  $message
     */
    private function shouldHandle(array $message): bool
    {
        if (($message['type'] ?? null) !== 'text' || ! isset($message['id'], $message['from'])) {
            return false;
        }

        $text = $message['text']['body'] ?? null;

        return is_string($text) && trim($text) !== '';
    }
}

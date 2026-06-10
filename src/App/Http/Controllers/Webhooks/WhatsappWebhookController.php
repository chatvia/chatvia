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
 * Receives WhatsApp Business Cloud API webhooks. The {secret} path segment is
 * the per-number `webhook_secret` (routes the event to the agent); it doubles
 * as the verify token of Meta's GET handshake, and POSTed events are verified
 * with Meta's signed payload (HMAC-SHA256 of the raw body with the app
 * secret, `X-Hub-Signature-256`). Each message is processed once (idempotency
 * on the wamid — Meta re-delivers for up to 7 days on non-200 and duplicates
 * even without failures) and handed to a queued job so the webhook answers
 * fast. POST always replies 200 — repeated non-200s make Meta retry and
 * eventually disable the subscription, and an unknown/spoofed secret should
 * just be a silent no-op.
 */
class WhatsappWebhookController
{
    use InteractsWithMetaWebhooks;

    /**
     * Meta verifies the Callback URL with a GET handshake when it's pasted
     * into the app's WhatsApp → Configuration settings.
     */
    public function verify(Request $request, string $secret): Response
    {
        return $this->metaChallengeResponse($request, $this->number($secret)?->webhook_secret);
    }

    public function receive(Request $request, string $secret): JsonResponse
    {
        $ok = response()->json(['ok' => true]);

        $number = $this->number($secret);

        if ($number === null || ! $this->hasValidMetaSignature($request, $number->app_secret) || $request->input('object') !== 'whatsapp_business_account') {
            return $ok;
        }

        // Events arrive batched: entry[] per WABA, changes[] per field,
        // messages[] per sender — loop every level.
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (! is_array($change) || ($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];

                $target = $this->routeByPhoneNumberId($number, $value['metadata']['phone_number_id'] ?? null);

                if ($target === null) {
                    continue;
                }

                $names = $this->contactNames($value);

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    if (! is_array($message) || ! $this->shouldHandle($message)) {
                        continue;
                    }

                    // Process each message at most once — Meta re-delivers
                    // with decreasing frequency for up to 7 days.
                    $mid = (string) $message['id'];
                    if (! Cache::add("wa:mid:{$target->id}:{$mid}", true, now()->addDays(7))) {
                        continue;
                    }

                    $waId = (string) $message['from'];

                    HandleWhatsappMessageJob::dispatch(
                        $target,
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

    private function number(string $secret): ?AgentWhatsappNumber
    {
        return AgentWhatsappNumber::query()
            ->where('webhook_secret', $secret)
            ->where('enabled', true)
            ->first();
    }

    /**
     * One Meta app has ONE callback URL, but can serve several WhatsApp
     * numbers (one per agent) — every number's events land on whichever
     * agent's URL is configured. `value.metadata.phone_number_id` names the
     * number that actually received the message, so re-route to its row.
     * Only a sibling sharing the same app secret is accepted: the signature
     * was verified against the URL row's secret, so an equal secret proves
     * the event came from that sibling's Meta app too — without the check, a
     * workspace could replay events into another workspace's agent.
     */
    private function routeByPhoneNumberId(AgentWhatsappNumber $urlNumber, ?string $phoneNumberId): ?AgentWhatsappNumber
    {
        if ($phoneNumberId === null || $phoneNumberId === $urlNumber->phone_number_id) {
            return $urlNumber;
        }

        $sibling = AgentWhatsappNumber::query()
            ->where('phone_number_id', $phoneNumberId)
            ->where('enabled', true)
            ->first();

        if ($sibling === null || ! hash_equals($urlNumber->app_secret, $sibling->app_secret)) {
            return null;
        }

        return $sibling;
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

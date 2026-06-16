<?php

namespace Domain\Channel\Whatsapp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper over the WhatsApp Business Cloud API (Meta Graph API — plain
 * HTTP, no SDK), called per access token (Chatvia is multi-number — one
 * WhatsApp business number per agent). Every call is best-effort: a Meta
 * outage logs and returns a safe value rather than throwing into the
 * webhook/job path.
 *
 * WhatsApp messages can't be edited, so replies don't stream — the adapter
 * keeps the typing indicator alive during generation and sends the full
 * reply at the end (chunked at WhatsApp's 4096-char limit). Replies are
 * always immediate responses to an inbound message, so they stay inside the
 * free-form 24-hour customer-service window.
 */
class WhatsappClient
{
    private const GRAPH = 'https://graph.facebook.com/v25.0';

    /** WhatsApp caps a single text body at 4096 chars. */
    private const MESSAGE_LIMIT = 4096;

    /**
     * Validate an access token + phone number id pair and return the number's
     * identity, or null if invalid.
     *
     * @return array{display_phone_number: ?string, verified_name: ?string}|null
     */
    public function numberInfo(string $token, string $phoneNumberId): ?array
    {
        $response = $this->call($token, 'get', "/{$phoneNumberId}", [
            'fields' => 'display_phone_number,verified_name',
        ]);

        if ($response === null) {
            return null;
        }

        return [
            'display_phone_number' => $response['display_phone_number'] ?? null,
            'verified_name' => $response['verified_name'] ?? null,
        ];
    }

    /**
     * Subscribe the Meta app to the WhatsApp Business Account's webhooks —
     * without this, configuring the Callback URL alone never delivers
     * messages. Best-effort (the dashboard's API Setup flow also does it for
     * test numbers).
     */
    public function subscribeWaba(string $token, string $wabaId): bool
    {
        return $this->call($token, 'post', "/{$wabaId}/subscribed_apps") !== null;
    }

    /**
     * Register the connected number for the Cloud API (POST /{phone_number_id}/
     * register with a 6-digit two-step-verification PIN) — required before a
     * freshly added number can send/receive. Best-effort: a number already
     * registered through Embedded Signup returns an error we treat as non-fatal.
     */
    public function registerNumber(string $token, string $phoneNumberId, string $pin): bool
    {
        return $this->call($token, 'post', "/{$phoneNumberId}/register", [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]) !== null;
    }

    /**
     * Send a reply (the model's Markdown rendered as WhatsApp markup — see
     * {@see WhatsappFormatter}), chunked at the 4096-char limit. The first
     * URL in each message gets a link preview.
     */
    public function sendMessage(string $token, string $phoneNumberId, string $to, string $markdown): void
    {
        $text = (new WhatsappFormatter)->toWhatsapp($markdown);

        foreach ($this->chunk($text) as $part) {
            $sent = $this->call($token, 'post', "/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $part],
            ]);

            if ($sent === null) {
                return;
            }
        }
    }

    /**
     * Mark the inbound message as read and show the "typing…" indicator in
     * one call. WhatsApp clears it after ~25s (or when the reply arrives), so
     * callers re-send it during a long generation to keep it alive.
     * Best-effort.
     */
    public function sendTypingIndicator(string $token, string $phoneNumberId, string $messageId): void
    {
        $this->call($token, 'post', "/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
            'typing_indicator' => ['type' => 'text'],
        ]);
    }

    /**
     * Call a Graph API endpoint; null on transport failure or a Graph error.
     *
     * @return array<string, mixed>|null
     */
    protected function call(string $token, string $method, string $path, array $params = []): ?array
    {
        try {
            $pending = Http::withToken($token);

            $response = $method === 'get'
                ? $pending->get(self::GRAPH.$path, $params)
                : $pending->asJson()->post(self::GRAPH.$path, (object) $params);

            $json = $response->json();

            if (! $response->successful() || ! is_array($json) || isset($json['error'])) {
                Log::warning("WhatsApp {$path} returned an error", ['error' => $json['error']['message'] ?? 'malformed response']);

                return null;
            }

            return $json;
        } catch (Throwable $e) {
            Log::warning("WhatsApp {$path} failed", ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        return mb_strlen($text) <= self::MESSAGE_LIMIT ? [$text] : mb_str_split($text, self::MESSAGE_LIMIT);
    }
}

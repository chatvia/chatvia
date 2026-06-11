<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Concerns\InteractsWithMetaWebhooks;
use Domain\Channel\Instagram\Jobs\HandleInstagramMessageJob;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Receives Instagram Messaging webhooks (Instagram Login flavour — the
 * payload shape mirrors Messenger's). The {secret} path segment is the
 * per-account `webhook_secret` (routes the event to the agent); it doubles as
 * the verify token of Meta's GET handshake, and POSTed events are verified
 * with Meta's signed payload (HMAC-SHA256 of the raw body with the Instagram
 * app secret, `X-Hub-Signature-256`). Each message is processed once
 * (idempotency on `mid` — Meta re-delivers on timeout) and handed to a queued
 * job so the webhook answers fast. POST always replies 200 — repeated non-2xx
 * makes Meta disable the subscription, and an unknown/spoofed secret should
 * just be a silent no-op.
 */
class InstagramWebhookController
{
    use InteractsWithMetaWebhooks;

    /**
     * Meta verifies the Callback URL with a GET handshake when it's pasted
     * into the app's Instagram webhook settings.
     */
    public function verify(Request $request, string $secret): Response
    {
        return $this->metaChallengeResponse($request, $this->account($secret)?->webhook_secret);
    }

    public function receive(Request $request, string $secret): JsonResponse
    {
        $ok = response()->json(['ok' => true]);

        $account = $this->account($secret);

        if ($account === null || ! $this->hasValidMetaSignature($request, $account->app_secret) || $request->input('object') !== 'instagram') {
            return $ok;
        }

        // Events arrive batched: entry[] per account, messaging[] per sender.
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                if (! is_array($event) || ! $this->shouldHandle($account, $event)) {
                    continue;
                }

                // Process each message at most once — Meta re-delivers with
                // decreasing frequency for up to 36 hours, so the 2-day TTL
                // outlives that window.
                $mid = $event['message']['mid'] ?? null;
                if ($mid !== null && ! Cache::add("ig:mid:{$account->id}:{$mid}", true, now()->addDays(2))) {
                    continue;
                }

                HandleInstagramMessageJob::dispatch(
                    $account,
                    (string) $event['sender']['id'],
                    (string) $event['message']['text'],
                );
            }
        }

        return $ok;
    }

    private function account(string $secret): ?AgentInstagramAccount
    {
        return AgentInstagramAccount::query()
            ->with('agent')
            ->where('webhook_secret', $secret)
            ->where('enabled', true)
            ->first();
    }

    /**
     * Answer plain text messages from users. Everything else — read receipts,
     * reactions, attachment-only messages (story replies/mentions arrive as
     * attachments), and crucially the account's own replies echoing back
     * (`is_echo` / sender == account) — is ignored, or the bot would answer
     * itself in a loop.
     *
     * @param  array<string, mixed>  $event
     */
    private function shouldHandle(AgentInstagramAccount $account, array $event): bool
    {
        $message = $event['message'] ?? null;

        if (! is_array($message) || ($message['is_echo'] ?? false)) {
            return false;
        }

        $text = $message['text'] ?? null;
        $sender = $event['sender']['id'] ?? null;

        if (! is_string($text) || trim($text) === '' || $sender === null) {
            return false;
        }

        return $account->ig_id === null || (string) $sender !== $account->ig_id;
    }
}

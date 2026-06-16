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
 * Receives Instagram Messaging webhooks for Chatvia's single Meta app. With one
 * app serving every workspace there's one app-level Callback URL (no per-account
 * secret in the path): the GET handshake is verified against the app-level
 * verify token, POSTed events against the app secret (HMAC-SHA256 of the raw
 * body, `X-Hub-Signature-256`). Instagram delivers `object="instagram"` with
 * each `entry.id` = the IG professional account id — route each entry to its
 * agent by `ig_id` (falling back to the messaging recipient). Each message is
 * processed once (idempotency on `mid`) and handed to a queued job so the
 * webhook answers fast. POST always replies 200 — repeated non-2xx makes Meta
 * disable the subscription, and an unknown/spoofed payload should be a silent
 * no-op.
 */
class InstagramWebhookController
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

        if ($appSecret === '' || ! $this->hasValidMetaSignature($request, $appSecret) || $request->input('object') !== 'instagram') {
            return $ok;
        }

        // Events arrive batched: entry[] per IG account, messaging[] per sender.
        foreach ((array) $request->input('entry', []) as $entry) {
            $messaging = (array) ($entry['messaging'] ?? []);

            // entry.id is the IG professional account id; fall back to the
            // recipient on the first event if a payload variant omits it.
            $igId = (string) ($entry['id'] ?? '');
            if ($igId === '' && isset($messaging[0]['recipient']['id'])) {
                $igId = (string) $messaging[0]['recipient']['id'];
            }

            $account = $this->account($igId);

            if ($account === null) {
                continue;
            }

            foreach ($messaging as $event) {
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

    private function account(string $igId): ?AgentInstagramAccount
    {
        if ($igId === '') {
            return null;
        }

        return AgentInstagramAccount::query()
            ->with('agent')
            ->where('ig_id', $igId)
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

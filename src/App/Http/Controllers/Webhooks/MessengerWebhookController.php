<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\Concerns\InteractsWithMetaWebhooks;
use Domain\Channel\Messenger\Jobs\HandleMessengerMessageJob;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Receives Messenger Platform webhooks. The {secret} path segment is the
 * per-page `webhook_secret` (routes the event to the agent); it doubles as
 * the verify token of Meta's GET handshake, and POSTed events are verified
 * with Meta's signed payload (HMAC-SHA256 of the raw body with the app
 * secret, `X-Hub-Signature-256`). Each message is processed once (idempotency
 * on `mid` — Meta re-delivers on timeout) and handed to a queued job so the
 * webhook answers fast. POST always replies 200 — repeated non-2xx makes
 * Meta disable the subscription, and an unknown/spoofed secret should just
 * be a silent no-op.
 */
class MessengerWebhookController
{
    use InteractsWithMetaWebhooks;

    /**
     * Meta verifies the Callback URL with a GET handshake when it's pasted
     * into the app's Messenger → Webhooks settings.
     */
    public function verify(Request $request, string $secret): Response
    {
        return $this->metaChallengeResponse($request, $this->page($secret)?->webhook_secret);
    }

    public function receive(Request $request, string $secret): JsonResponse
    {
        $ok = response()->json(['ok' => true]);

        $page = $this->page($secret);

        if ($page === null || ! $this->hasValidMetaSignature($request, $page->app_secret) || $request->input('object') !== 'page') {
            return $ok;
        }

        // Events arrive batched: entry[] per page, messaging[] per sender.
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                if (! is_array($event) || ! $this->shouldHandle($page, $event)) {
                    continue;
                }

                // Process each message at most once — Meta re-delivers with
                // decreasing frequency for up to 36 hours.
                $mid = $event['message']['mid'] ?? null;
                if ($mid !== null && ! Cache::add("msgr:mid:{$page->id}:{$mid}", true, now()->addDays(2))) {
                    continue;
                }

                HandleMessengerMessageJob::dispatch(
                    $page,
                    (string) $event['sender']['id'],
                    (string) $event['message']['text'],
                );
            }
        }

        return $ok;
    }

    private function page(string $secret): ?AgentMessengerPage
    {
        return AgentMessengerPage::query()
            ->where('webhook_secret', $secret)
            ->where('enabled', true)
            ->first();
    }

    /**
     * Answer plain text messages from users. Everything else — delivery
     * receipts, postbacks, attachment-only messages, and crucially the page's
     * own replies echoing back (`is_echo` / sender == page) — is ignored, or
     * the bot would answer itself in a loop.
     *
     * @param  array<string, mixed>  $event
     */
    private function shouldHandle(AgentMessengerPage $page, array $event): bool
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

        return $page->page_id === null || (string) $sender !== $page->page_id;
    }
}

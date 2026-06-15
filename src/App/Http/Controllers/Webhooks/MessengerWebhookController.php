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
 * Receives Messenger Platform webhooks for Chatvia's single Meta app. With one
 * app serving every workspace there's one app-level Callback URL (no per-page
 * secret in the path): the GET handshake is verified against the app-level
 * verify token, POSTed events against the app secret (HMAC-SHA256 of the raw
 * body, `X-Hub-Signature-256`), and each `entry` is routed to the right agent
 * by its Page id (`entry.id`). Each message is processed once (idempotency on
 * `mid` — Meta re-delivers on timeout) and handed to a queued job so the
 * webhook answers fast. POST always replies 200 — repeated non-2xx makes Meta
 * disable the subscription, and an unknown/spoofed payload should be a silent
 * no-op.
 */
class MessengerWebhookController
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

        if ($appSecret === '' || ! $this->hasValidMetaSignature($request, $appSecret) || $request->input('object') !== 'page') {
            return $ok;
        }

        // Events arrive batched: entry[] per page, messaging[] per sender. One
        // app-level URL receives every connected page, so route each entry to
        // its agent by Page id.
        foreach ((array) $request->input('entry', []) as $entry) {
            $page = $this->page((string) ($entry['id'] ?? ''));

            if ($page === null) {
                continue;
            }

            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                if (! is_array($event) || ! $this->shouldHandle($page, $event)) {
                    continue;
                }

                // Process each message at most once — Meta re-delivers with
                // decreasing frequency for up to 36 hours, so the 2-day TTL
                // outlives that window.
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

    private function page(string $pageId): ?AgentMessengerPage
    {
        if ($pageId === '') {
            return null;
        }

        return AgentMessengerPage::query()
            ->with('agent')
            ->where('page_id', $pageId)
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

<?php

namespace App\Http\Controllers\Webhooks;

use Domain\Channel\Slack\Jobs\HandleSlackEventJob;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Receives Slack Events API callbacks. The {secret} path segment is the
 * per-app `webhook_secret` (routes the event to the agent); authenticity is
 * then verified with Slack's signed-secrets scheme (HMAC-SHA256 of
 * `v0:{timestamp}:{body}` with the app's signing secret, ±5 min replay
 * window). Each event is processed once (idempotency on `event_id` — Slack
 * re-delivers on timeout) and handed to a queued job so the webhook answers
 * within Slack's 3-second deadline. Always replies 200 — a non-2xx makes
 * Slack retry and eventually disable the subscription, and an unknown/spoofed
 * secret should just be a silent no-op.
 */
class SlackWebhookController
{
    /** Reject requests whose timestamp is outside this window (replay protection). */
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $ok = response()->json(['ok' => true]);

        $app = AgentSlackApp::query()
            ->with('agent')
            ->where('webhook_secret', $secret)
            ->where('enabled', true)
            ->first();

        if ($app === null || ! $this->hasValidSignature($request, $app)) {
            return $ok;
        }

        // Slack verifies the Request URL with a one-off challenge when it's
        // pasted into the app's Event Subscriptions page.
        if ($request->input('type') === 'url_verification') {
            return response()->json(['challenge' => $request->input('challenge')]);
        }

        // Process each event at most once — Slack retries a failed delivery at
        // most 3 times within minutes, so the 1-day TTL comfortably covers it.
        $eventId = $request->input('event_id');
        if ($eventId !== null && ! Cache::add("slack:event:{$app->id}:{$eventId}", true, now()->addDay())) {
            return $ok;
        }

        $event = $request->input('event');

        if (is_array($event) && $this->shouldHandle($app, $event)) {
            HandleSlackEventJob::dispatch(
                $app,
                (string) $event['channel'],
                (string) $event['text'],
                $event['thread_ts'] ?? null,
            );
        }

        return $ok;
    }

    private function hasValidSignature(Request $request, AgentSlackApp $app): bool
    {
        $timestamp = (string) $request->header('X-Slack-Request-Timestamp');

        if ($timestamp === '' || abs(now()->getTimestamp() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:".$request->getContent(), $app->signing_secret);

        return hash_equals($expected, (string) $request->header('X-Slack-Signature'));
    }

    /**
     * Answer `app_mention`s (the bot @mentioned in a channel) and direct
     * messages. Everything else — channel chatter that doesn't mention the
     * bot, message edits/joins (subtyped), and crucially any bot message
     * including our own replies echoing back — is ignored, or the bot would
     * answer itself in a loop.
     *
     * @param  array<string, mixed>  $event
     */
    private function shouldHandle(AgentSlackApp $app, array $event): bool
    {
        $text = $event['text'] ?? null;

        if (! is_string($text) || trim($text) === '' || ! isset($event['channel'])) {
            return false;
        }

        if (isset($event['bot_id']) || isset($event['subtype']) || ($event['user'] ?? null) === $app->bot_user_id) {
            return false;
        }

        return match ($event['type'] ?? null) {
            'app_mention' => true,
            'message' => ($event['channel_type'] ?? null) === 'im',
            default => false,
        };
    }
}

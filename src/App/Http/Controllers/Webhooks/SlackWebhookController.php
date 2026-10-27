<?php

namespace App\Http\Controllers\Webhooks;

use Domain\Channel\Slack\Actions\ReceiveSlackEventAction;
use Domain\Channel\Slack\Data\SlackEventData;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Slack Events API callbacks. The {secret} path segment is the
 * per-app `webhook_secret` (routes the event to the agent); authenticity is
 * then verified with Slack's signed-secrets scheme (HMAC-SHA256 of
 * `v0:{timestamp}:{body}` with the app's signing secret, ±5 min replay
 * window). Verified events go to {@see ReceiveSlackEventAction}, which
 * de-duplicates and queues them so the webhook answers within Slack's 3-second
 * deadline. Always replies 200 — a non-2xx makes Slack retry and eventually
 * disable the subscription, and an unknown/spoofed secret should just be a
 * silent no-op.
 */
class SlackWebhookController
{
    /** Reject requests whose timestamp is outside this window (replay protection). */
    private const int TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function __invoke(Request $request, ReceiveSlackEventAction $receive, string $secret): JsonResponse
    {
        $ok = response()->json(['ok' => true]);

        $app = AgentSlackApp::query()
            ->with('agent')
            ->where('webhook_secret', $secret)
            ->where('is_enabled', true)
            ->first();

        if ($app === null || ! $this->hasValidSignature($request, $app)) {
            return $ok;
        }

        // Slack verifies the Request URL with a one-off challenge when it's
        // pasted into the app's Event Subscriptions page.
        if ($request->input('type') === 'url_verification') {
            return response()->json(['challenge' => $request->input('challenge')]);
        }

        $receive->execute($app, SlackEventData::fromPayload($request->all()));

        return $ok;
    }

    private function hasValidSignature(Request $request, AgentSlackApp $app): bool
    {
        $timestamp = (string) $request->header('X-Slack-Request-Timestamp');

        if ($timestamp === '' || abs(now()->getTimestamp() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$request->getContent()}", $app->signing_secret);

        return hash_equals($expected, (string) $request->header('X-Slack-Signature'));
    }
}

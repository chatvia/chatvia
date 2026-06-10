<?php

namespace App\Http\Controllers\Webhooks;

use Domain\Channel\Teams\Jobs\HandleTeamsActivityJob;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsActivityVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Receives Bot Framework activities for Microsoft Teams. The {secret} path
 * segment is the per-bot `webhook_secret` (routes the activity to the agent);
 * authenticity is then verified through the connector's Bearer JWT — signed
 * with Microsoft's published keys, audience = the bot's App ID (see
 * {@see TeamsActivityVerifier}). Each activity is processed once (idempotency
 * on the activity id — the connector re-delivers on timeout) and handed to a
 * queued job so the webhook answers within the connector's 15-second deadline.
 * Always replies 200 — repeated non-2xx makes the connector back off and drop
 * activities, and an unknown/spoofed secret should just be a silent no-op.
 */
class TeamsWebhookController
{
    public function __invoke(Request $request, TeamsActivityVerifier $verifier, string $secret): JsonResponse
    {
        $ok = response()->json(['ok' => true]);

        $bot = AgentTeamsBot::query()
            ->where('webhook_secret', $secret)
            ->where('enabled', true)
            ->first();

        if ($bot === null) {
            return $ok;
        }

        $activity = $request->json()->all();

        if (! $verifier->verify($request->header('Authorization'), $bot->app_id, $activity['serviceUrl'] ?? null)) {
            return $ok;
        }

        if (! $this->shouldHandle($activity)) {
            return $ok;
        }

        // Process each activity at most once (the connector re-delivers on timeout).
        $activityId = $activity['id'] ?? null;
        if (is_string($activityId) && ! Cache::add("msteams:activity:{$bot->id}:{$activityId}", true, now()->addDay())) {
            return $ok;
        }

        HandleTeamsActivityJob::dispatch($bot, $activity);

        return $ok;
    }

    /**
     * Answer user message activities only. Everything else — conversation
     * updates, reactions, typing echoes, and crucially anything sent by a bot
     * (including our own replies, would they ever come back) — is ignored, or
     * the bot could answer itself in a loop. In channels Teams already
     * delivers a message only when the bot is @mentioned, so no extra
     * mention filtering is needed here.
     *
     * @param  array<string, mixed>  $activity
     */
    private function shouldHandle(array $activity): bool
    {
        $text = $activity['text'] ?? null;

        if (($activity['type'] ?? null) !== 'message' || ! is_string($text) || trim($text) === '') {
            return false;
        }

        if (! is_string($activity['conversation']['id'] ?? null) || ! is_string($activity['serviceUrl'] ?? null)) {
            return false;
        }

        $from = $activity['from'] ?? [];

        return ($from['role'] ?? null) !== 'bot'
            && ($from['id'] ?? null) !== ($activity['recipient']['id'] ?? null);
    }
}

<?php

namespace App\Http\Controllers\Webhooks;

use Domain\Channel\Teams\Actions\ReceiveTeamsActivityAction;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsActivityVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Bot Framework activities for Microsoft Teams. The {secret} path
 * segment is the per-bot `webhook_secret` (routes the activity to the agent);
 * authenticity is then verified through the connector's Bearer JWT — signed
 * with Microsoft's published keys, audience = the bot's App ID (see
 * {@see TeamsActivityVerifier}). Verified activities go to
 * {@see ReceiveTeamsActivityAction}, which filters, de-duplicates and queues
 * them so the webhook answers within the connector's 15-second deadline.
 * Always replies 200 — repeated non-2xx makes the connector back off and drop
 * activities, and an unknown/spoofed secret should just be a silent no-op.
 */
class TeamsWebhookController
{
    public function __invoke(Request $request, TeamsActivityVerifier $verifier, ReceiveTeamsActivityAction $receive, string $secret): JsonResponse
    {
        $ok = response()->json(['ok' => true]);

        $bot = AgentTeamsBot::query()
            ->with('agent')
            ->where('webhook_secret', $secret)
            ->where('is_enabled', true)
            ->first();

        if ($bot === null) {
            return $ok;
        }

        // Authenticate before touching the payload — the body is parsed only
        // once the JWT has proven the request genuine.
        $claims = $verifier->verify($request->header('Authorization'), $bot->app_id);

        if ($claims === null) {
            return $ok;
        }

        $activity = $request->json()->all();

        // Defence in depth: a replayed token can't redirect replies — the
        // activity's serviceUrl must match the token's serviceurl claim.
        if (! is_string($activity['serviceUrl'] ?? null) || ! $verifier->matchesServiceUrl($claims, $activity['serviceUrl'])) {
            return $ok;
        }

        $receive->execute($bot, $activity);

        return $ok;
    }
}

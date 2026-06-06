<?php

namespace Domain\Widget\Services;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Pusher\Pusher;

/**
 * Authorises a widget visitor's WebSocket subscription against a presence
 * channel. Encapsulates the defence-in-depth check:
 *  - the channel name must be presence-chat.<conversationExternalId>
 *  - the conversation must exist and belong to a Session whose external_id
 *    matches the widget's session id
 *  - if an Agent is resolvable from the widget, the conversation's session
 *    must also belong to that agent (no cross-agent presence-channel reuse).
 *
 * Returns the Pusher auth payload on success, or an array {error, status}
 * the controller can hand back as JSON. Kept here so the rule is unit-testable
 * without a full HTTP round-trip.
 */
readonly class BroadcastAuthorizer
{
    /**
     * @return array{error: string, status: int}|array<string, mixed>
     */
    public function authorize(
        string $channelName,
        string $sessionExternalId,
        string $socketId,
        ?Agent $agent = null,
    ): array {
        if (! preg_match('/^presence-chat\.(.+)$/', $channelName, $matches)) {
            return ['error' => 'Invalid channel', 'status' => 403];
        }

        $conversationExternalId = $matches[1];

        $conversation = Conversation::query()
            ->where('external_id', $conversationExternalId)
            ->whereHas('session', function ($q) use ($sessionExternalId, $agent): void {
                $q->where('external_id', $sessionExternalId);
                if ($agent !== null) {
                    $q->where('agent_id', $agent->id);
                }
            })
            ->first();

        if (! $conversation) {
            return ['error' => 'Unauthorized', 'status' => 403];
        }

        $userName = $conversation->displayName() ?? 'Visitor';

        $payload = $this->pusher()->authorizePresenceChannel(
            $channelName,
            $socketId,
            $sessionExternalId,
            [
                'name' => $userName,
                'type' => 'user',
                'session_id' => $sessionExternalId,
            ],
        );

        return (array) json_decode($payload, true);
    }

    private function pusher(): Pusher
    {
        return new Pusher(
            config('broadcasting.connections.reverb.key'),
            config('broadcasting.connections.reverb.secret'),
            config('broadcasting.connections.reverb.app_id'),
            [
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
                'useTLS' => config('broadcasting.connections.reverb.options.useTLS'),
            ],
        );
    }
}

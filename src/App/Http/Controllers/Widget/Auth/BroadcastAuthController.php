<?php

namespace App\Http\Controllers\Widget\Auth;

use Domain\Conversation\Models\Conversation;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pusher\Pusher;

/**
 * Handles WebSocket/Pusher authentication for widget users.
 * Widget users don't have traditional auth, so we use session-based auth.
 */
readonly class BroadcastAuthController
{
    public function __construct(private WidgetVerification $widgetVerification) {}

    public function __invoke(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        if (! $sessionId || ! $channelName || ! $socketId) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        // Verify domain is allowed for this agent
        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        // Extract conversation ID from channel name (e.g., "presence-chat.01HXYZ...")
        if (! preg_match('/^presence-chat\.(.+)$/', $channelName, $matches)) {
            return response()->json(['error' => 'Invalid channel'], 403);
        }

        $conversationId = $matches[1];

        // Verify the session owns this conversation AND the conversation's
        // session belongs to the requesting agent — defence in depth against
        // cross-agent presence-channel takeover.
        $agent = current_agent_from_widget();

        $conversation = Conversation::query()
            ->where('external_id', $conversationId)
            ->whereHas('session', function ($q) use ($sessionId, $agent): void {
                $q->where('external_id', $sessionId);
                if ($agent !== null) {
                    $q->where('agent_id', $agent->id);
                }
            })
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get user info from session/lead for presence data
        $userName = $conversation->displayName() ?? 'Visitor';

        // Generate Pusher auth response for presence channel
        $pusher = new Pusher(
            config('broadcasting.connections.reverb.key'),
            config('broadcasting.connections.reverb.secret'),
            config('broadcasting.connections.reverb.app_id'),
            [
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
                'useTLS' => config('broadcasting.connections.reverb.options.useTLS'),
            ]
        );

        $presenceData = [
            'user_id' => $sessionId,
            'user_info' => [
                'name' => $userName,
                'type' => 'user',
                'session_id' => $sessionId,
            ],
        ];

        $auth = $pusher->authorizePresenceChannel($channelName, $socketId, $sessionId, $presenceData['user_info']);

        return response()->json(json_decode($auth, true));
    }
}

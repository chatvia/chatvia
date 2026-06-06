<?php

namespace App\Http\Controllers\Widget\Auth;

use Domain\Widget\Services\BroadcastAuthorizer;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles WebSocket/Pusher authentication for widget users.
 * Widget users don't have traditional auth, so we use session-based auth.
 */
readonly class BroadcastAuthController
{
    public function __construct(
        private WidgetVerification $widgetVerification,
        private BroadcastAuthorizer $authorizer,
    ) {}

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

        $result = $this->authorizer->authorize($channelName, $sessionId, $socketId, current_agent_from_widget());

        if (isset($result['error'], $result['status'])) {
            return response()->json(['error' => $result['error']], $result['status']);
        }

        return response()->json($result);
    }
}

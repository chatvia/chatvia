<?php

namespace App\Http\Controllers\Widget\Auth;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Widget\Enums\BroadcastDenialReason;
use Domain\Widget\Services\BroadcastAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WebSocket channel authentication for widget visitors. They have no login,
 * so access to a conversation's presence channel is granted by session
 * ownership instead (see {@see BroadcastAuthorizer}).
 */
readonly class BroadcastAuthController
{
    use InteractsWithWidgetRequest;

    public function __construct(
        private BroadcastAuthorizer $authorizer,
    ) {}

    public function __invoke(Request $request, #[WidgetAgent] ?Agent $agent): JsonResponse
    {
        $sessionId = $this->widgetSessionId($request);
        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        if ($sessionId === null || blank($channelName) || is_array($channelName) || blank($socketId) || is_array($socketId)) {
            return response()->json(['error' => 'Missing required parameters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $result = $this->authorizer->authorize($channelName, $sessionId, $socketId, $agent);

        if ($result instanceof BroadcastDenialReason) {
            return response()->json(['error' => $result->value], JsonResponse::HTTP_FORBIDDEN);
        }

        return response()->json($result);
    }
}

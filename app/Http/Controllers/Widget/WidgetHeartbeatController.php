<?php

namespace App\Http\Controllers\Widget;

use App\Models\ChatConversation;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class WidgetHeartbeatController
{
    public function __construct(
        private WidgetVerification $widgetVerification,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id') ?? $request->header('X-THREAD-ID');

        if (! $sessionId) {
            return response()->json(['error' => 'Missing session'], 400);
        }

        // Verify domain is allowed for this agent
        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        // Update conversation's last_activity_at if conversation_id provided
        if ($conversationId) {
            ChatConversation::where('id', $conversationId)
                ->whereHas('session', fn ($q) => $q->where('id', $sessionId))
                ->update(['last_activity_at' => now()]);
        }

        return response()->json(['success' => true]);
    }
}

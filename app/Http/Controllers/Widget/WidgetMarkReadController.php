<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatMemory;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class WidgetMarkReadController
{
    public function __construct(
        private WidgetVerification $widgetVerification,
        private ChatMemory $chatMemory,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id') ?? $request->header('X-THREAD-ID');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $agent = current_agent_from_widget();

        if (! $agent) {
            return response()->json(['error' => 'Agent not found'], 404);
        }

        $markedCount = $this->chatMemory
            ->setAgent($agent)
            ->setSessionId($sessionId)
            ->setConversationId($conversationId)
            ->markMessagesAsRead('user'); // User is reading agent messages

        return response()->json([
            'marked_count' => $markedCount,
        ]);
    }
}

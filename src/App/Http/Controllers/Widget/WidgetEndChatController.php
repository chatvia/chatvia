<?php

namespace App\Http\Controllers\Widget;

use App\Widget\WidgetVerification;
use Domain\Chat\Actions\EndHandoffChatAction;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class WidgetEndChatController
{
    public function __construct(private WidgetVerification $widgetVerification) {}

    public function __invoke(Request $request, EndHandoffChatAction $endHandoff): JsonResponse
    {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $conversation = ChatConversation::where('external_id', $conversationId)
            ->whereHas('session', fn ($q) => $q->where('external_id', $sessionId))
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        if (! $endHandoff->execute($conversation)) {
            return response()->json(['success' => true, 'message' => 'Conversation not in handoff mode']);
        }

        return response()->json(['success' => true, 'message' => 'Live chat ended']);
    }
}

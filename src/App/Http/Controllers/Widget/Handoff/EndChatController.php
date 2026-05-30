<?php

namespace App\Http\Controllers\Widget\Handoff;

use App\Widget\WidgetVerification;
use Domain\Chat\Actions\EndHandoffChatAction;
use Domain\Chat\Actions\FindWidgetConversationAction;
use Domain\Widget\Data\WidgetEndChatResultData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class EndChatController
{
    public function __construct(private WidgetVerification $widgetVerification) {}

    public function __invoke(
        Request $request,
        EndHandoffChatAction $endHandoff,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetEndChatResultData {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $conversation = $findConversation->execute($conversationId, $sessionId, current_agent_from_widget());

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return $endHandoff->execute($conversation)
            ? WidgetEndChatResultData::ended()
            : WidgetEndChatResultData::notInHandoff();
    }
}

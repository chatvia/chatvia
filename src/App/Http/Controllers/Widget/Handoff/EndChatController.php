<?php

namespace App\Http\Controllers\Widget\Handoff;

use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Actions\Handoff\EndHandoffChatAction;
use Domain\Widget\Data\Session\WidgetEndChatResultData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class EndChatController
{
    public function __invoke(
        Request $request,
        EndHandoffChatAction $endHandoff,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetEndChatResultData {
        $sessionId = $request->header('X-SESSION-ID');
        $conversationId = $request->input('conversation_id');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing required parameters'], 400);
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

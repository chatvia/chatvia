<?php

namespace App\Http\Controllers\Widget\Handoff;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Actions\Handoff\EndHandoffChatAction;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Data\Session\WidgetEndChatResultData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The visitor ends a live chat with a human operator; the AI answers again.
 */
readonly class EndChatController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        EndHandoffChatAction $endHandoff,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetEndChatResultData {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $request->input('conversation_id');

        if ($sessionId === null || ! is_string($conversationId) || $conversationId === '') {
            return response()->json(['error' => 'Missing required parameters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $conversation = $findConversation->execute($conversationId, $sessionId, $agent, $this->widgetViewer($request));

        if (! $conversation instanceof Conversation) {
            return response()->json(['error' => 'Conversation not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $endHandoff->execute($conversation)
            ? WidgetEndChatResultData::ended()
            : WidgetEndChatResultData::notInHandoff();
    }
}

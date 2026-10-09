<?php

namespace App\Http\Controllers\Widget\Handoff;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Data\Handoff\WidgetHandoffStatusData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Polled by the widget: is a human operator answering this conversation? A
 * conversation that does not belong to the visitor reads as "AI answers".
 */
readonly class HandoffStatusController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetHandoffStatusData {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $request->input('conversation_id') ?? $this->widgetConversationId($request);

        if ($sessionId === null || ! $conversationId) {
            return response()->json(['error' => 'Missing required parameters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $conversationId = (string) $conversationId;
        $conversation = $findConversation->execute($conversationId, $sessionId, $agent);

        return $conversation instanceof Conversation
            ? WidgetHandoffStatusData::fromConversation($conversation, $conversationId)
            : WidgetHandoffStatusData::unhandedOff($conversationId);
    }
}

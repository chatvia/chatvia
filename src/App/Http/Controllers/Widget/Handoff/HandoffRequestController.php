<?php

namespace App\Http\Controllers\Widget\Handoff;

use App\Http\Requests\Widget\WidgetHandoffRequest;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Actions\Handoff\RequestHandoffAction;
use Domain\Widget\Data\Handoff\WidgetHandoffRequestResultData;
use Illuminate\Http\JsonResponse;

readonly class HandoffRequestController
{
    public function __invoke(
        WidgetHandoffRequest $request,
        RequestHandoffAction $requestHandoff,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetHandoffRequestResultData {
        $sessionId = $request->header('X-SESSION-ID');
        $conversationId = $request->string('conversation_id')->toString();

        if (! $sessionId) {
            return response()->json(['error' => 'Missing session'], 400);
        }

        $conversation = $findConversation->execute($conversationId, $sessionId, current_agent_from_widget());

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return WidgetHandoffRequestResultData::make($requestHandoff->execute($conversation), $conversationId);
    }
}

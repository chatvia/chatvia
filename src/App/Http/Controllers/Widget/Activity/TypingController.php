<?php

namespace App\Http\Controllers\Widget\Activity;

use App\Http\Requests\Widget\WidgetTypingRequest;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Actions\Messages\BroadcastChatTypingAction;
use Domain\Widget\Data\Auth\WidgetAckData;
use Illuminate\Http\JsonResponse;

class TypingController
{
    public function __invoke(
        WidgetTypingRequest $request,
        BroadcastChatTypingAction $broadcastTyping,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetAckData {
        $conversation = $findConversation->execute(
            $request->string('conversation_id')->toString(),
            (string) $request->header('X-SESSION-ID'),
            current_agent_from_widget(),
        );

        if (! $conversation) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $broadcastTyping->execute($request->toData($conversation));

        return new WidgetAckData;
    }
}

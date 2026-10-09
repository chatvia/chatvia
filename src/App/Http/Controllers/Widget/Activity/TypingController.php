<?php

namespace App\Http\Controllers\Widget\Activity;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Widget\WidgetTypingRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Actions\Messages\BroadcastChatTypingAction;
use Domain\Widget\Data\Auth\WidgetAckData;
use Illuminate\Http\JsonResponse;

/**
 * Broadcasts the visitor's typing indicator to the operator watching a
 * handed-off conversation.
 */
class TypingController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        WidgetTypingRequest $request,
        #[WidgetAgent] Agent $agent,
        BroadcastChatTypingAction $broadcastTyping,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetAckData {
        $conversation = $findConversation->execute(
            $request->string('conversation_id')->toString(),
            (string) $this->widgetSessionId($request),
            $agent,
        );

        if (! $conversation) {
            return response()->json(['error' => 'Unauthorized'], JsonResponse::HTTP_FORBIDDEN);
        }

        $broadcastTyping->execute($request->toData($conversation));

        return new WidgetAckData;
    }
}

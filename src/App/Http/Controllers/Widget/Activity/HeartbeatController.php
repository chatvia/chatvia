<?php

namespace App\Http\Controllers\Widget\Activity;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Activity\TouchConversationActivityAction;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Data\Auth\WidgetAckData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Periodic "still here" ping from an open widget. Moves the conversation's
 * last-activity time forward so it counts as live in the operator inbox. A
 * hidden (never used) conversation is not stamped.
 */
readonly class HeartbeatController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        FindWidgetConversationAction $findConversation,
        TouchConversationActivityAction $touchActivity,
    ): JsonResponse|WidgetAckData {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $request->input('conversation_id') ?? $this->widgetConversationId($request);

        if ($sessionId === null) {
            return response()->json(['error' => 'Missing session'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (! $conversationId) {
            return new WidgetAckData;
        }

        // The conversation must belong to this session and agent, or anyone
        // with a guessed id could move another visitor's activity time.
        $conversation = $findConversation->execute((string) $conversationId, $sessionId, $agent, $this->widgetViewer($request));

        if (! $conversation instanceof Conversation) {
            return response()->json(['error' => 'Conversation not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $touchActivity->execute($conversation);

        return new WidgetAckData;
    }
}

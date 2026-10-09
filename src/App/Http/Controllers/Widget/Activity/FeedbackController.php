<?php

namespace App\Http\Controllers\Widget\Activity;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Widget\WidgetFeedbackRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Activity\RecordMessageFeedbackAction;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Widget\Data\Auth\WidgetAckData;
use Illuminate\Http\JsonResponse;

/**
 * Stores a visitor's thumbs-up or thumbs-down on an assistant reply. The
 * conversation must belong to the visitor's session on this agent.
 */
class FeedbackController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        WidgetFeedbackRequest $request,
        #[WidgetAgent] Agent $agent,
        FindWidgetConversationAction $findConversation,
        RecordMessageFeedbackAction $recordFeedback,
    ): JsonResponse|WidgetAckData {
        $sessionId = (string) $this->widgetSessionId($request);
        $conversationId = (string) $this->widgetConversationId($request);

        if (! $findConversation->execute($conversationId, $sessionId, $agent)) {
            return response()->json(['error' => 'Conversation not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $rating = $request->toData();

        $recordFeedback->execute(new ChatScopeData($agent, $sessionId, $conversationId), $rating->messageId, $rating->feedback);

        return new WidgetAckData;
    }
}

<?php

namespace App\Http\Controllers\Widget\Activity;

use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Data\Auth\WidgetAckData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController
{
    public function __invoke(Request $request, ChatMemory $memory, WidgetVerification $widgetVerification, FindWidgetConversationAction $findConversation): JsonResponse|WidgetAckData
    {
        $agentId = $request->header('X-AGENT-ID');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $agent = current_agent_from_widget();
        $sessionId = (string) $request->header('X-SESSION-ID');
        $conversationId = (string) $request->header('X-CONVERSATION-ID');

        // Verify the conversation belongs to (agent, requesting session) before
        // writing feedback — consistent with the other activity endpoints.
        if (! $findConversation->execute($conversationId, $sessionId, $agent)) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $memory
            ->withContext($agent, $sessionId, $conversationId)
            ->feedback($request->header('X-MESSAGE-ID'), $request->boolean('feedback') ? 'positive' : 'negative');

        return new WidgetAckData;
    }
}

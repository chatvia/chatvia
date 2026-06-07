<?php

namespace App\Http\Controllers\Widget\Conversations;

use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Data\Auth\WidgetAckData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationDeleteController
{
    public function __invoke(
        Request $request,
        ChatMemory $memory,
        WidgetVerification $widgetVerification,
    ): JsonResponse|WidgetAckData {
        $agentId = $request->header('X-AGENT-ID');

        // Same domain check the sibling controllers run — without it any
        // origin could delete threads on a public agent.
        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $agent = current_agent_from_widget();
        $widgetUser = $request->attributes->get('widget_user');
        $sessionId = $request->header('X-SESSION-ID');
        $conversationId = $request->header('X-CONVERSATION-ID');

        // On a protected agent, only the session's owning chatter may delete its
        // threads — block deleting another signed-in visitor's history (including
        // the delete-all branch) by replaying their client-supplied session id.
        if ($widgetUser && ! $widgetVerification->verifySessionExternalId($sessionId, $agent, $widgetUser)) {
            return response()->json(['error' => 'Invalid session'], 404);
        }

        $memory
            ->withContext($agent, $sessionId);

        if ($conversationId) {
            // Confirm the conversation actually belongs to THIS agent (and, on a
            // protected agent, the authenticated chatter) before touching it —
            // guarantees a conversation id from another agent's or chatter's
            // session can't be deleted by spoofing X-AGENT-ID / X-SESSION-ID.
            $thread = $widgetVerification->verifyConversationId($conversationId, $agent, $widgetUser);

            if (! $thread) {
                return response()->json(['error' => 'Invalid thread'], 404);
            }

            $chatMemory = $memory->setConversationId($conversationId);

            if ($chatMemory->conversationExists()) {
                $chatMemory->deleteConversation(deletedBy: 'user');
            }
        } else {
            $memory->deleteAllConversations();
        }

        return new WidgetAckData;
    }
}

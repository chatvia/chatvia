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
        $sessionId = $request->header('X-SESSION-ID');
        $conversationId = $request->header('X-CONVERSATION-ID');

        $memory
            ->withContext($agent, $sessionId);

        if ($conversationId) {
            // Confirm the conversation actually belongs to THIS agent before
            // touching it — guarantees a conversation id from another agent's
            // session can't be deleted by spoofing X-AGENT-ID.
            $thread = $widgetVerification->verifyConversationId($conversationId, $agent);

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

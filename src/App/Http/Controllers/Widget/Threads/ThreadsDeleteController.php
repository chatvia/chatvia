<?php

namespace App\Http\Controllers\Widget\Threads;

use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Data\Auth\WidgetAckData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreadsDeleteController
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
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        $agent = current_agent_from_widget();
        $sessionId = $request->header('X-SESSION-ID');
        $threadId = $request->header('X-THREAD-ID');

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($sessionId);

        if ($threadId) {
            // Confirm the thread actually belongs to THIS agent before
            // touching it — guarantees a thread id from another agent's
            // session can't be deleted by spoofing X-AGENT-ID.
            $thread = $widgetVerification->verifyThreadId($threadId, $agent);

            if (! $thread) {
                return response()->json(['error' => 'Invalid thread'], 404);
            }

            $chatMemory = $memory->setConversationId($threadId);

            if ($chatMemory->conversationExists()) {
                $chatMemory->deleteConversation(deletedBy: 'user');
            }
        } else {
            $memory->deleteAllConversations();
        }

        return new WidgetAckData;
    }
}

<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Services\Memory\ChatMemory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentChatLogDeleteConversationController
{
    public function __invoke(Request $request, Agent $agent, ChatMemory $chatMemory, string $conversationId): JsonResponse
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.chat.delete'), 403);

        // Find the conversation scoped to the current agent for security
        $conversation = ChatConversation::whereHas('session', function ($query) use ($agent) {
            $query->where('agent_id', $agent->id);
        })->where('external_id', $conversationId)->firstOrFail();

        // Get the session before deleting the conversation
        $session = $conversation->session;

        $chatMemory
            ->setAgent($agent)
            ->setExternalSessionId($session->external_id)
            ->setConversationId($conversation->external_id)
            ->deleteConversation();

        $conversation->delete();

        // Delete session if it's empty and inactive for more than 30 days
        if ($session) {
            $remainingConversations = $session->conversations()->count();

            if ($remainingConversations === 0 &&
                $session->last_activity_at &&
                $session->last_activity_at->lt(now()->subDays(30))
            ) {
                $session->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully.',
        ]);
    }
}

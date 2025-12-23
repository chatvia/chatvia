<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Models\Agent;
use App\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentChatLogDeleteConversationController
{
    public function __invoke(Request $request, Agent $agent, string $conversationId): JsonResponse
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.chat.delete'), 403);

        // Find the conversation
        $conversation = ChatConversation::find($conversationId);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);
        }

        // Verify conversation belongs to this agent
        if ($conversation->session?->agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this conversation.',
            ], 403);
        }

        // Delete the conversation (cascade will delete messages, session, etc.)
        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully.',
        ]);
    }
}

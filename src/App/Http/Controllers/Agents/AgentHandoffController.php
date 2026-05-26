<?php

namespace App\Http\Controllers\Agents;

use App\Http\Requests\Agents\AgentHandoffMessageRequest;
use App\Http\Requests\Agents\AgentHandoffTypingRequest;
use Domain\Agents\Models\Agent;
use Domain\Chat\Actions\BroadcastChatTypingAction;
use Domain\Chat\Actions\ListPendingHandoffsAction;
use Domain\Chat\Actions\MarkHandoffConversationReadAction;
use Domain\Chat\Actions\ReturnConversationToAiAction;
use Domain\Chat\Actions\SendHandoffAdminMessageAction;
use Domain\Chat\Actions\TakeoverConversationAction;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgentHandoffController
{
    public function index(Agent $agent, ListPendingHandoffsAction $listPending): JsonResponse
    {
        if (Gate::denies('access-agent', $agent)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $pending = $listPending->execute($agent);

        return response()->json(['pending' => $pending, 'count' => $pending->count()]);
    }

    public function takeover(
        Request $request,
        Agent $agent,
        ChatConversation $conversation,
        TakeoverConversationAction $takeover,
    ): JsonResponse {
        if ($error = $this->guardAccess($agent, $conversation)) {
            return $error;
        }

        if (! has_permission_to_agent($agent, 'agent.chat.takeover')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to take over conversations.',
            ], 403);
        }

        if (! $takeover->execute($conversation, $request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to take over this conversation. It may already be handled by another admin.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'You have taken over this conversation.',
            'handoff_status' => 'human',
            'user_name' => $conversation->displayName(),
            'user_email' => $conversation->displayEmail(),
        ]);
    }

    public function return(
        Request $request,
        Agent $agent,
        ChatConversation $conversation,
        ReturnConversationToAiAction $returnToAi,
    ): JsonResponse {
        if ($error = $this->guardAccess($agent, $conversation)) {
            return $error;
        }

        if (! $returnToAi->execute($conversation, $request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to return this conversation to AI.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversation has been returned to AI.',
            'handoff_status' => 'ai',
        ]);
    }

    public function message(
        AgentHandoffMessageRequest $request,
        Agent $agent,
        ChatConversation $conversation,
        SendHandoffAdminMessageAction $sendMessage,
    ): JsonResponse {
        if ($error = $this->guardAccess($agent, $conversation)) {
            return $error;
        }

        $user = $request->user();

        if (! $conversation->isHandedOff() || $conversation->handoff_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You must take over this conversation before sending messages.',
            ], 403);
        }

        $message = $sendMessage->execute($request->toData($conversation));

        if (! $message) {
            return response()->json(['success' => false, 'message' => 'Failed to send message.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'content' => $message->content,
                'role' => $message->role,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }

    public function typing(
        AgentHandoffTypingRequest $request,
        Agent $agent,
        ChatConversation $conversation,
        BroadcastChatTypingAction $broadcastTyping,
    ): JsonResponse {
        if ($error = $this->guardAccess($agent, $conversation)) {
            return $error;
        }

        $user = $request->user();

        if (! $conversation->isHandedOff() || $conversation->handoff_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You must take over this conversation before sending typing indicators.',
            ], 403);
        }

        $broadcastTyping->execute($request->toData($conversation));

        return response()->json(['success' => true]);
    }

    public function markRead(
        Agent $agent,
        ChatConversation $conversation,
        MarkHandoffConversationReadAction $markRead,
    ): JsonResponse {
        $session = $conversation->session;

        if (! $session || $session->agent_id !== $agent->id) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return response()->json([
            'marked_count' => $markRead->execute($agent, $conversation),
        ]);
    }

    private function guardAccess(Agent $agent, ChatConversation $conversation): ?JsonResponse
    {
        if (Gate::denies('access-agent', $agent) || $conversation->session->agent_id !== $agent->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        return null;
    }
}

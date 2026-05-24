<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Services\HandoffService;
use Domain\Chat\Services\Memory\ChatMemory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

readonly class AgentHandoffController
{
    public function __construct(
        private HandoffService $handoffService,
        private ChatMemory $chatMemory,
    ) {}

    /**
     * List conversations with pending handoff requests for an agent.
     */
    public function index(Request $request, Agent $agent): JsonResponse
    {
        if (Gate::denies('access-agent', $agent)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $pending = ChatConversation::query()
            ->with(['session.member', 'lead', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->whereHas('session', fn ($q) => $q->where('agent_id', $agent->id))
            ->where('handoff_status', 'pending')
            ->where('visible', true)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (ChatConversation $conversation) {
                $lastMessage = $conversation->messages->first();
                $lead = $conversation->lead;
                $member = $conversation->session?->member;

                return [
                    'conversation_id' => $conversation->id,
                    'user_name' => $lead?->name ?? $member?->name ?? 'Visitor',
                    'user_email' => $lead?->email ?? $member?->email,
                    'last_message' => $lastMessage?->content,
                    'last_message_at' => $lastMessage?->created_at?->toIso8601String(),
                    'requested_at' => $conversation->updated_at->toIso8601String(),
                    'session_id' => $conversation->chat_session_id,
                ];
            });

        return response()->json(['pending' => $pending, 'count' => $pending->count()]);
    }

    public function takeover(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        if ($error = $this->guardAccess($agent, $conversation)) {
            return $error;
        }

        if (! has_permission_to_agent($agent, 'agent.chat.takeover')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to take over conversations.',
            ], 403);
        }

        $success = $this->handoffService->takeOver($conversation, $request->user());

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to take over this conversation. It may already be handled by another admin.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'You have taken over this conversation.',
            'handoff_status' => 'human',
            'user_name' => $this->handoffService->getUserName($conversation),
            'user_email' => $this->handoffService->getUserEmail($conversation),
        ]);
    }

    public function return(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        if ($error = $this->guardAccess($agent, $conversation)) {
            return $error;
        }

        $success = $this->handoffService->returnToAi($conversation, $request->user());

        if (! $success) {
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

    public function message(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        if ($error = $this->guardAccess($agent, $conversation)) {
            return $error;
        }

        $user = $request->user();

        if (! $this->handoffService->isHandedOff($conversation) || $conversation->handoff_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You must take over this conversation before sending messages.',
            ], 403);
        }

        $message = $this->handoffService->sendAdminMessage($conversation, $user, $request->input('content'));

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

    public function typing(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        $request->validate([
            'is_typing' => ['required', 'boolean'],
        ]);

        if ($error = $this->guardAccess($agent, $conversation)) {
            return $error;
        }

        $user = $request->user();

        if (! $this->handoffService->isHandedOff($conversation) || $conversation->handoff_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You must take over this conversation before sending typing indicators.',
            ], 403);
        }

        $this->handoffService->broadcastTyping(
            conversationId: $conversation->external_id,
            isTyping: $request->boolean('is_typing'),
            typerType: 'agent',
            typerName: $user->name,
            typerId: (string) $user->id,
        );

        return response()->json(['success' => true]);
    }

    public function markRead(Agent $agent, ChatConversation $conversation): JsonResponse
    {
        $session = $conversation->session;

        if (! $session || $session->agent_id !== $agent->id) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        $markedCount = $this->chatMemory
            ->setAgent($agent)
            ->setExternalSessionId($session->external_id)
            ->setConversationId($conversation->external_id)
            ->markMessagesAsRead('admin');

        return response()->json(['marked_count' => $markedCount]);
    }

    /**
     * Common access guard for handoff actions tied to a specific conversation.
     */
    private function guardAccess(Agent $agent, ChatConversation $conversation): ?JsonResponse
    {
        if (Gate::denies('access-agent', $agent) || $conversation->session?->agent_id !== $agent->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        return null;
    }
}

<?php

namespace App\Http\Controllers\Agents\Handoff;

use App\Models\Agent;
use App\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lists conversations with pending handoff requests for an agent.
 */
readonly class HandoffPendingListController
{
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns the agent
        if ($agent->user_id !== $user->id) {
            return response()->json([
                'error' => 'Unauthorized',
            ], 403);
        }

        $pendingConversations = ChatConversation::query()
            ->with(['session.member', 'lead', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->whereHas('session', fn ($q) => $q->where('agent_id', $agent->id))
            ->where('handoff_status', 'pending')
            ->orderBy('updated_at', 'desc')
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

        return response()->json([
            'pending' => $pendingConversations,
            'count' => $pendingConversations->count(),
        ]);
    }
}

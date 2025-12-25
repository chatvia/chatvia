<?php

namespace App\Http\Controllers\Agents\Handoff;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Services\Chat\HandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

readonly class HandoffTypingController
{
    public function __construct(private HandoffService $handoffService) {}

    public function __invoke(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        $request->validate([
            'is_typing' => ['required', 'boolean'],
        ]);

        $user = auth()->user();

        // Check access (handles both ownership and team permissions)
        if (Gate::denies('access-agent', $agent) || $conversation->session?->agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Check if conversation is handed off to this user
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
}

<?php

namespace App\Http\Controllers\Agents\Handoff;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Services\Chat\HandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

readonly class HandoffMessageController
{
    public function __construct(private HandoffService $handoffService) {}

    public function __invoke(Request $request, Agent $agent, string $conversation): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        $user = auth()->user();

        // Resolve conversation
        $conversation = ChatConversation::find($conversation);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);
        }

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
                'message' => 'You must take over this conversation before sending messages.',
            ], 403);
        }

        $message = $this->handoffService->sendAdminMessage(
            $conversation,
            $user,
            $request->input('content')
        );

        if (! $message) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message.',
            ], 500);
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
}

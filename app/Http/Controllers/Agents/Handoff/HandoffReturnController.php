<?php

namespace App\Http\Controllers\Agents\Handoff;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Services\Chat\HandoffService;
use Illuminate\Http\JsonResponse;

readonly class HandoffReturnController
{
    public function __construct(private HandoffService $handoffService) {}

    public function __invoke(Agent $agent, string $conversation): JsonResponse
    {
        $user = auth()->user();

        // Resolve conversation
        $conversation = ChatConversation::find($conversation);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found.',
            ], 404);
        }

        // Check ownership
        if ($agent->user_id !== $user->id || $conversation->session?->agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $success = $this->handoffService->returnToAi($conversation, $user);

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
}

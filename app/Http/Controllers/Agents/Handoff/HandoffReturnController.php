<?php

namespace App\Http\Controllers\Agents\Handoff;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Services\Chat\HandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

readonly class HandoffReturnController
{
    public function __construct(private HandoffService $handoffService) {}

    public function __invoke(Agent $agent, ChatConversation $conversation): JsonResponse
    {
        $user = auth()->user();

        // Check access (handles both ownership and team permissions)
        if (Gate::denies('access-agent', $agent) || $conversation->session?->agent_id !== $agent->id) {
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

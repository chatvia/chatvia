<?php

namespace App\Http\Controllers\Agents\Handoff;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Services\Chat\HandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

readonly class HandoffTakeOverController
{
    public function __construct(private HandoffService $handoffService) {}

    public function __invoke(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Check if user can access the agent (handles both ownership and team permissions)
        // and conversation belongs to the agent
        if (Gate::denies('access-agent', $agent) || $conversation->session?->agent_id !== $agent->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to take over this conversation.',
            ], 403);
        }

        // Check if user has takeover permission
        if (! has_permission_to_agent($agent, 'agent.chat.takeover')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to take over conversations.',
            ], 403);
        }

        $success = $this->handoffService->takeOver($conversation, $user);

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
}

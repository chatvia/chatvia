<?php

namespace App\Http\Controllers\Widget;

use App\Events\Chat\ChatHandoffEvent;
use App\Models\ChatConversation;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles widget user requests for human assistance.
 * Sets the conversation to "pending" status so admins can see and accept.
 */
readonly class WidgetHandoffRequestController
{
    public function __construct(private WidgetVerification $widgetVerification) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => ['required', 'string'],
        ]);

        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id');

        if (! $sessionId) {
            return response()->json(['error' => 'Missing session'], 400);
        }

        // Verify domain is allowed for this agent
        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        // Verify session owns this conversation
        $conversation = ChatConversation::where('id', $conversationId)
            ->whereHas('session', fn ($q) => $q->where('id', $sessionId))
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Check if already handed off
        if ($conversation->handoff_status === 'human') {
            return response()->json([
                'status' => 'human',
                'message' => 'Already connected to a human agent.',
            ]);
        }

        // Check if already pending
        if ($conversation->handoff_status === 'pending') {
            return response()->json([
                'status' => 'pending',
                'message' => 'Your request is pending. Please wait for an agent.',
            ]);
        }

        // Set to pending
        $conversation->update([
            'handoff_status' => 'pending',
        ]);

        // Broadcast the pending status (admin dashboard can listen to this)
        ChatHandoffEvent::dispatch(
            $conversation->id,
            'pending',
            null,
            null,
        );

        return response()->json([
            'status' => 'pending',
            'conversation_id' => $conversationId,
            'message' => 'Your request has been sent. Please wait for an agent to connect.',
        ]);
    }
}

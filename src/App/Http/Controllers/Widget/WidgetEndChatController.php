<?php

namespace App\Http\Controllers\Widget;

use Domain\Chat\Events\ChatHandoffEvent;
use Domain\Chat\Models\ChatConversation;
use App\Widget\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class WidgetEndChatController
{
    public function __construct(private WidgetVerification $widgetVerification) {}

    public function __invoke(Request $request): JsonResponse
    {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        // Verify domain is allowed for this agent
        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        // Verify session owns this conversation
        $conversation = ChatConversation::where('external_id', $conversationId)
            ->whereHas('session', fn ($q) => $q->where('external_id', $sessionId))
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Only process if conversation is currently handed off
        if ($conversation->handoff_status !== 'human') {
            return response()->json([
                'success' => true,
                'message' => 'Conversation not in handoff mode',
            ]);
        }

        // Get admin info before resetting
        $adminId = $conversation->handoff_user_id;
        $adminName = $conversation->handoffUser?->name;

        // Reset handoff status
        $conversation->update([
            'handoff_status' => 'ai',
            'handoff_user_id' => null,
            'handoff_at' => null,
        ]);

        // Broadcast the event so admin knows user ended the chat
        ChatHandoffEvent::dispatch(
            $conversationId,
            'user_ended',
            (string) $adminId,
            $adminName,
        );

        return response()->json([
            'success' => true,
            'message' => 'Live chat ended',
        ]);
    }
}

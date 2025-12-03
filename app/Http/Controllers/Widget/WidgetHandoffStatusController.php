<?php

namespace App\Http\Controllers\Widget;

use App\Models\ChatConversation;
use App\Services\Chat\HandoffService;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class WidgetHandoffStatusController
{
    public function __construct(
        private HandoffService $handoffService,
        private WidgetVerification $widgetVerification,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $sessionId = $request->header('x-session-id');
        $agentId = $request->header('x-agent-id');
        $conversationId = $request->input('conversation_id') ?? $request->header('x-thread-id');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing required parameters'], 400);
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

        $isHandedOff = $this->handoffService->isHandedOff($conversation);

        return response()->json([
            'conversation_id' => $conversationId,
            'handoff_status' => $conversation->handoff_status ?? 'ai',
            'is_human_controlled' => $isHandedOff,
            'admin_name' => $isHandedOff ? $conversation->handoffUser?->name : null,
        ]);
    }
}

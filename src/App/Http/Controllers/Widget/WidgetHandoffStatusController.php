<?php

namespace App\Http\Controllers\Widget;

use App\Widget\WidgetVerification;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Services\HandoffService;
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
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id') ?? $request->header('X-THREAD-ID');

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

        // If conversation doesn't exist (deleted or tampered ID),
        // return default AI mode instead of 404
        if (! $conversation) {
            return response()->json([
                'conversation_id' => $conversationId,
                'handoff_status' => 'ai',
                'is_human_controlled' => false,
                'admin_name' => null,
            ]);
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

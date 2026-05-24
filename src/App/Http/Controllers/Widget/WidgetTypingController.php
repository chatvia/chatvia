<?php

namespace App\Http\Controllers\Widget;

use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Services\HandoffService;
use App\Widget\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class WidgetTypingController
{
    public function __construct(
        private HandoffService $handoffService,
        private WidgetVerification $widgetVerification,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => ['required', 'string'],
            'is_typing' => ['required', 'boolean'],
        ]);

        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id');

        // Verify domain is allowed for this agent
        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        // Verify session owns this conversation
        $conversation = ChatConversation::where('external_id', $conversationId)
            ->whereHas('session', fn ($q) => $q->where('external_id', $sessionId))
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get user name for the typing indicator
        $userName = $this->getUserName($conversation);

        $this->handoffService->broadcastTyping(
            $conversationId,
            $request->boolean('is_typing'),
            'user',
            $userName,
            $sessionId,
        );

        return response()->json(['success' => true]);
    }

    private function getUserName(ChatConversation $conversation): string
    {
        $lead = $conversation->lead;
        if ($lead && $lead->name) {
            return $lead->name;
        }

        $member = $conversation->session?->member;
        if ($member && $member->name) {
            return $member->name;
        }

        return 'Visitor';
    }
}

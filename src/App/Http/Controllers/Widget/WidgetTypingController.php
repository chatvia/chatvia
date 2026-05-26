<?php

namespace App\Http\Controllers\Widget;

use App\Http\Requests\Widget\WidgetTypingRequest;
use App\Widget\WidgetVerification;
use Domain\Chat\Actions\BroadcastChatTypingAction;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Http\JsonResponse;

class WidgetTypingController
{
    public function __construct(
        private readonly WidgetVerification $widgetVerification,
    ) {}

    public function __invoke(WidgetTypingRequest $request, BroadcastChatTypingAction $broadcastTyping): JsonResponse
    {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->string('conversation_id')->toString();

        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $conversation = ChatConversation::where('external_id', $conversationId)
            ->whereHas('session', fn ($q) => $q->where('external_id', $sessionId))
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $broadcastTyping->execute($request->toData($conversation));

        return response()->json(['success' => true]);
    }
}

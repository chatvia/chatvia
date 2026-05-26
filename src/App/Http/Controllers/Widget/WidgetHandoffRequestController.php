<?php

namespace App\Http\Controllers\Widget;

use App\Widget\WidgetVerification;
use Domain\Chat\Actions\RequestHandoffAction;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class WidgetHandoffRequestController
{
    public function __construct(private WidgetVerification $widgetVerification) {}

    public function __invoke(Request $request, RequestHandoffAction $requestHandoff): JsonResponse
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

        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $conversation = ChatConversation::where('external_id', $conversationId)
            ->whereHas('session', fn ($q) => $q->where('external_id', $sessionId))
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        $status = $requestHandoff->execute($conversation);

        $messages = [
            'human' => 'Already connected to a human agent.',
            'pending' => 'Your request has been sent. Please wait for an agent to connect.',
        ];

        return response()->json([
            'status' => $status,
            'conversation_id' => $conversationId,
            'message' => $messages[$status] ?? 'Your request is pending. Please wait for an agent.',
        ]);
    }
}

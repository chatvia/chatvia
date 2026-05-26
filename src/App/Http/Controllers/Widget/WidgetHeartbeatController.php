<?php

namespace App\Http\Controllers\Widget;

use App\Widget\WidgetVerification;
use Domain\Chat\Actions\RecordConversationActivityAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class WidgetHeartbeatController
{
    public function __construct(
        private WidgetVerification $widgetVerification,
    ) {}

    public function __invoke(Request $request, RecordConversationActivityAction $recordActivity): JsonResponse
    {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id') ?? $request->header('X-THREAD-ID');

        if (! $sessionId) {
            return response()->json(['error' => 'Missing session'], 400);
        }

        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        if ($conversationId) {
            $recordActivity->execute($conversationId);
        }

        return response()->json(['success' => true]);
    }
}

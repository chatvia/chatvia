<?php

namespace App\Http\Controllers\Widget\Handoff;

use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Widget\Data\Handoff\WidgetHandoffStatusData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class HandoffStatusController
{
    public function __construct(
        private WidgetVerification $widgetVerification,
    ) {}

    public function __invoke(Request $request, FindWidgetConversationAction $findConversation): JsonResponse|WidgetHandoffStatusData
    {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id') ?? $request->header('X-CONVERSATION-ID');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $conversation = $findConversation->execute(
            (string) $conversationId,
            (string) $sessionId,
            current_agent_from_widget(),
        );

        return $conversation
            ? WidgetHandoffStatusData::fromConversation($conversation, (string) $conversationId)
            : WidgetHandoffStatusData::unhandedOff((string) $conversationId);
    }
}

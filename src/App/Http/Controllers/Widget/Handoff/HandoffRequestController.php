<?php

namespace App\Http\Controllers\Widget\Handoff;

use App\Http\Requests\Widget\WidgetHandoffRequest;
use App\Widget\WidgetVerification;
use Domain\Chat\Actions\FindWidgetConversationAction;
use Domain\Chat\Actions\RequestHandoffAction;
use Domain\Widget\Data\WidgetHandoffRequestResultData;
use Illuminate\Http\JsonResponse;

readonly class HandoffRequestController
{
    public function __construct(private WidgetVerification $widgetVerification) {}

    public function __invoke(
        WidgetHandoffRequest $request,
        RequestHandoffAction $requestHandoff,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetHandoffRequestResultData {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->string('conversation_id')->toString();

        if (! $sessionId) {
            return response()->json(['error' => 'Missing session'], 400);
        }

        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $conversation = $findConversation->execute($conversationId, $sessionId, current_agent_from_widget());

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return WidgetHandoffRequestResultData::make($requestHandoff->execute($conversation), $conversationId);
    }
}

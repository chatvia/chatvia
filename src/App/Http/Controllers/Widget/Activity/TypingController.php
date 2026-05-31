<?php

namespace App\Http\Controllers\Widget\Activity;

use App\Http\Requests\Widget\WidgetTypingRequest;
use App\Widget\WidgetVerification;
use Domain\Chat\Actions\BroadcastChatTypingAction;
use Domain\Chat\Actions\FindWidgetConversationAction;
use Domain\Widget\Data\WidgetAckData;
use Illuminate\Http\JsonResponse;

class TypingController
{
    public function __construct(
        private readonly WidgetVerification $widgetVerification,
    ) {}

    public function __invoke(
        WidgetTypingRequest $request,
        BroadcastChatTypingAction $broadcastTyping,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetAckData {
        $agentId = $request->header('X-AGENT-ID');

        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $conversation = $findConversation->execute(
            $request->string('conversation_id')->toString(),
            (string) $request->header('X-SESSION-ID'),
            current_agent_from_widget(),
        );

        if (! $conversation) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $broadcastTyping->execute($request->toData($conversation));

        return new WidgetAckData;
    }
}

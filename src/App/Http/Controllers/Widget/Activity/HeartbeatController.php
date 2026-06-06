<?php

namespace App\Http\Controllers\Widget\Activity;

use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Data\Auth\WidgetAckData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class HeartbeatController
{
    public function __construct(
        private WidgetVerification $widgetVerification,
    ) {}

    public function __invoke(
        Request $request,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetAckData {
        $sessionId = $request->header('X-SESSION-ID');
        $agentId = $request->header('X-AGENT-ID');
        $conversationId = $request->input('conversation_id') ?? $request->header('X-CONVERSATION-ID');

        if (! $sessionId) {
            return response()->json(['error' => 'Missing session'], 400);
        }

        if ($agentId && ! $this->widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        if ($conversationId) {
            // Confirm the conversation belongs to the requesting session AND
            // agent — without this, anyone with a guessed conversation id
            // could falsify activity timestamps for other visitors.
            $conversation = $findConversation->execute(
                $conversationId,
                $sessionId,
                current_agent_from_widget(),
            );

            if (! $conversation) {
                return response()->json(['error' => 'Conversation not found'], 404);
            }

            Conversation::where('external_id', $conversationId)
                ->update(['last_activity_at' => now()]);
        }

        return new WidgetAckData;
    }
}

<?php

namespace App\Http\Controllers\Widget\Activity;

use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Data\Conversation\WidgetMarkReadResultData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class MarkReadController
{
    public function __construct(
        private ChatMemory $chatMemory,
    ) {}

    public function __invoke(
        Request $request,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetMarkReadResultData {
        $sessionId = $request->header('X-SESSION-ID');
        $conversationId = $request->input('conversation_id') ?? $request->header('X-CONVERSATION-ID');

        if (! $sessionId || ! $conversationId) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        $agent = current_agent_from_widget();

        if (! $agent) {
            return response()->json(['error' => 'Agent not found'], 404);
        }

        // Confirm the conversation belongs to this (agent, session) pair
        // before flipping any read flags — without this, anyone with a
        // valid conversation id could mark another visitor's messages read.
        $conversation = $findConversation->execute($conversationId, $sessionId, $agent);

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return new WidgetMarkReadResultData(
            marked_count: $this->chatMemory
                ->withContext($agent, $sessionId, $conversationId)
                ->markMessagesAsRead('user'),
        );
    }
}

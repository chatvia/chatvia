<?php

namespace App\Http\Controllers\Widget\Activity;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Activity\MarkMessagesReadAction;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\MessageReader;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Data\Conversation\WidgetMarkReadResultData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read receipts from the widget: the visitor has seen the operator's replies
 * in a handed-off conversation.
 */
readonly class MarkReadController
{
    use InteractsWithWidgetRequest;

    public function __construct(
        private MarkMessagesReadAction $markRead,
    ) {}

    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse|WidgetMarkReadResultData {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $request->input('conversation_id') ?? $this->widgetConversationId($request);

        if ($sessionId === null || ! $conversationId) {
            return response()->json(['error' => 'Missing required parameters'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Without this check anyone with a conversation id could mark another
        // visitor's messages as read.
        if (! $findConversation->execute((string) $conversationId, $sessionId, $agent) instanceof Conversation) {
            return response()->json(['error' => 'Conversation not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new WidgetMarkReadResultData(
            marked_count: $this->markRead->execute(
                new ChatScopeData($agent, $sessionId, (string) $conversationId),
                MessageReader::Visitor,
            ),
        );
    }
}

<?php

namespace App\Http\Controllers\Widget\Conversations;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\MessageAttachmentHydrator;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The message history of one widget conversation, with each message's
 * attachments, for a history reload or a conversation switch.
 */
class ConversationMessageController
{
    use InteractsWithWidgetRequest;

    /**
     * @return JsonResponse|array<int, array<string, mixed>>
     */
    public function __invoke(
        Request $request,
        ChatMemory $memory,
        WidgetVerification $verification,
        MessageAttachmentHydrator $hydrator,
    ): JsonResponse|array {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $this->widgetConversationId($request);

        if ($sessionId === null || $conversationId === null) {
            return response()->json(['error' => 'Missing thread/session ID'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $agent = $this->widgetAgent();

        // The conversation must belong to this agent, or a conversation id from
        // agent A could be read while faking agent B. On a protected agent it
        // must also belong to the signed-in chatter, so one chatter cannot read
        // another's thread by replaying their ids.
        $conversation = $verification->verifyConversationId($conversationId, $agent, $this->widgetUser($request));

        abort_if(! $conversation, JsonResponse::HTTP_FORBIDDEN, 'Invalid thread');

        $chatMemory = $memory->withContext($agent, $sessionId, $conversationId);

        // A conversation missing from this session's memory (tampered or
        // deleted) reads as empty; the widget then starts a new one.
        if (! $chatMemory->conversationExists()) {
            return [];
        }

        // Attachments are added so their chips show under each user message.
        return $hydrator->hydrate($chatMemory->getMessagesForConversation(), $conversation);
    }
}

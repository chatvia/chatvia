<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\MessageAttachmentHydrator;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\Request;

class ConversationMessageController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, TurnState $session, MessageAttachmentHydrator $hydrator): array
    {
        $agent = current_agent();

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateSession($agent))
            ->setConversationId($conversationId);

        abort_if(
            boolean: ! $memory->conversationExists(),
            code: 404
        );

        $messages = $memory->getMessagesForConversation();

        // Hydrate each message with its attachments so UserChatBubble can
        // render the file chips per-bubble.
        $conversation = Conversation::query()
            ->where('external_id', $conversationId)
            ->first();

        if ($conversation) {
            $messages = $hydrator->hydrate($messages, $conversation);
        }

        return $messages;
    }
}

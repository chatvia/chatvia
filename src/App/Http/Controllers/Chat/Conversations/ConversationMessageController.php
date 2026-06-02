<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ConversationMessageController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, ChatSession $session): array
    {
        $agent = current_agent();

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateChatSession($agent))
            ->setConversationId($conversationId);

        abort_if(
            boolean: ! $memory->conversationExist(),
            code: 404
        );

        return $memory->getMessagesForConversation();
    }
}

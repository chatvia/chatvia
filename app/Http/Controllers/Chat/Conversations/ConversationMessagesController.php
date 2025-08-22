<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ConversationMessagesController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, ChatSession $session)
    {
        $memory
            ->setAgent(current_agent())
            ->setSessionId($session->firstOrCreateChatSession())
            ->setConversationId($conversationId);

        abort_if(
            boolean: ! $memory->conversationExist(),
            code: 404
        );

        return $memory->getMessagesForConversation();
    }
}

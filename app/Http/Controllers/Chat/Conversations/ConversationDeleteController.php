<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ConversationDeleteController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, ChatSession $session)
    {
        $memory
            ->setAgent(current_agent())
            ->setSessionId($session->firstOrCreateChatSession())
            ->setConversationId($conversationId)
            ->deleteConversation();
    }
}

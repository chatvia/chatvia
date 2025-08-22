<?php

namespace App\Http\Controllers\Chat;

use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ChatSourcesController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, ChatSession $session)
    {
        return $memory
            ->setAgent(current_agent())
            ->setSessionId($session->firstOrCreateChatSession())
            ->setConversationId($conversationId)
            ->getSources();
    }
}

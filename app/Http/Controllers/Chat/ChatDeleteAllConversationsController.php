<?php

namespace App\Http\Controllers\Chat;

use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ChatDeleteAllConversationsController
{
    public function __invoke(Request $request, ChatMemory $memory, ChatSession $session)
    {
        $memory
            ->setAgent(current_agent())
            ->setSessionId($session->firstOrCreateChatSession())
            ->deleteAllConversations();
    }
}

<?php

namespace App\Http\Controllers\Chat;

use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ChatDeleteAllConversationsController
{
    public function __invoke(Request $request, ChatMemory $memory, ChatSession $session)
    {
        $agent = current_agent();

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateChatSession($agent))
            ->deleteAllConversations();
    }
}

<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ConversationsController
{
    public function __invoke(Request $request, ChatMemory $memory, ChatSession $session)
    {
        return $memory
            ->setAgent(current_agent())
            ->setSessionId($session->firstOrCreateChatSession())
            ->getConversationsForCurrentSession(
                onlyVisible: true,
                orderByDate: true,
            );
    }
}

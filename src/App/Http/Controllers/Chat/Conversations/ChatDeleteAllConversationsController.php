<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\Request;

class ChatDeleteAllConversationsController
{
    public function __invoke(Request $request, ChatMemory $memory, TurnState $session): void
    {
        $agent = current_agent();

        $memory
            ->withContext($agent, $session->firstOrCreateSession($agent))
            ->deleteAllConversations();
    }
}

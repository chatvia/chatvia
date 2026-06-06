<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\Request;

class ConversationController
{
    public function __invoke(Request $request, ChatMemory $memory, TurnState $session): array
    {
        $agent = current_agent();

        return $memory
            ->withContext($agent, $session->firstOrCreateSession($agent))
            ->getConversationsForCurrentSession(
                onlyVisible: true,
                orderByDate: true,
            );
    }
}

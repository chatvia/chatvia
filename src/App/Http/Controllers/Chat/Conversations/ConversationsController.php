<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ConversationsController
{
    public function __invoke(Request $request, ChatMemory $memory, ChatSession $session)
    {
        $agent = current_agent();

        return $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateChatSession($agent))
            ->getConversationsForCurrentSession(
                onlyVisible: true,
                orderByDate: true,
            );
    }
}

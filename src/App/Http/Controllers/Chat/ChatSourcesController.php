<?php

namespace App\Http\Controllers\Chat;

use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ChatSourcesController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, ChatSession $session)
    {
        $agent = current_agent();

        return $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateChatSession($agent))
            ->setConversationId($conversationId)
            ->getSources();
    }
}

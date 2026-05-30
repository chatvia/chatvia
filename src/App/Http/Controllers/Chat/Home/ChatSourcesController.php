<?php

namespace App\Http\Controllers\Chat\Home;

use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ChatSourcesController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, ChatSession $session): array
    {
        $agent = current_agent();

        return $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateChatSession($agent))
            ->setConversationId($conversationId)
            ->getSources();
    }
}

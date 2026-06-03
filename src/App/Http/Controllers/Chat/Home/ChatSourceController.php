<?php

namespace App\Http\Controllers\Chat\Home;

use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\Request;

class ChatSourceController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory, TurnState $session): array
    {
        $agent = current_agent();

        return $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateSession($agent))
            ->setConversationId($conversationId)
            ->getSources();
    }
}

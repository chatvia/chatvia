<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Chat\Services\Memory\ChatMemory;
use Illuminate\Http\Request;

class ConversationUpdate
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory)
    {
        $memory
            ->setAgent(current_agent())
            ->setConversationId($conversationId)
            ->updateConversationTitle($request->input('title'));
    }
}

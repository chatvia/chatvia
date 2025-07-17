<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Support\Chat\ChatMemory;
use Illuminate\Http\Request;

class ConversationDeleteController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory)
    {
        $memory->setAgent(current_agent())->setConversationId($conversationId)->deleteConversation();
    }
}

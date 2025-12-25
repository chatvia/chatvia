<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Models\ChatConversation;
use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use App\Services\Memory\ConversationManager;
use Illuminate\Http\Request;

class ConversationDeleteController
{
    public function __invoke(Request $request, string $conversationId,  ChatMemory $memory)
    {
        $conversation = ChatConversation::find($conversationId);

        $memory
            ->setAgent(current_agent())
            ->setSessionId($conversation->chat_session_id)
            ->setConversationId($conversationId)
            ->deleteConversation();
    }
}

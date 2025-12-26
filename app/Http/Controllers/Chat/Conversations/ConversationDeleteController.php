<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Models\ChatConversation;
use App\Services\Memory\ChatMemory;
use Illuminate\Http\Request;

class ConversationDeleteController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory)
    {
        $conversation = ChatConversation::where('external_id', $conversationId)->first();

        $memory
            ->setAgent(current_agent())
            ->setExternalSessionId($conversation->chat_session_id)
            ->setConversationId($conversationId)
            ->deleteConversation();
    }
}

<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Models\ChatConversation;
use App\Services\Memory\ChatMemory;
use Illuminate\Http\Request;

class ConversationDeleteController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory)
    {
        $conversation = ChatConversation::where('external_id', $conversationId)->firstOrFail();

        $memory
            ->setAgent(current_agent())
            ->setExternalSessionId($conversation->session->external_id)
            ->setConversationId($conversationId)
            ->deleteConversation();
    }
}

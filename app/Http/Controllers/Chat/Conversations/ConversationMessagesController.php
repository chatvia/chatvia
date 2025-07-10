<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Support\ChatMemory;
use Illuminate\Http\Request;

class ConversationMessagesController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $conversations)
    {
        abort_if(
            ! $conversations->conversationExist($conversationId), 404);

        return $conversations->getMessagesForConversation($conversationId);
    }
}

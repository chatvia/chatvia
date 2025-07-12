<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Support\ChatMemory;
use Illuminate\Http\Request;

class ConversationMessagesController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory)
    {
        $memory->setConversationId($conversationId);

        abort_if(
            boolean: ! $memory->conversationExist(),
            code: 404
        );

        return $memory->getMessagesForConversation(true);
    }
}

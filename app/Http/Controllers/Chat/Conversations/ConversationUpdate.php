<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Support\ChatMemory;
use Illuminate\Http\Request;

class ConversationUpdate
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $conversations)
    {
        $conversations->updateConversationTitle($conversationId, $request->input('title'));
    }
}

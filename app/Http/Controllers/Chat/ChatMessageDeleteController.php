<?php

namespace App\Http\Controllers\Chat;

use App\Support\Chat\ChatMemory;
use Illuminate\Http\Request;

class ChatMessageDeleteController
{
    public function __invoke(Request $request, ChatMemory $memory)
    {
        $request->validate([
            'conversation_id' => ['required', 'string'],
            'message_id' => ['required', 'string'],
        ]);

        $memory
            ->setAgent(current_agent())
            ->setConversationId($request->input('conversation_id'))
            ->deleteMessage($request->input('message_id'));
    }
}

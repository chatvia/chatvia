<?php

namespace App\Http\Controllers\Chat;

use App\Support\ChatMemory;
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
            ->setConversationId($request->input('conversation_id'))
            ->deleteMessage($request->input('message_id'));
    }
}

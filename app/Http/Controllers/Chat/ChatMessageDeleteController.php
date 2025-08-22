<?php

namespace App\Http\Controllers\Chat;

use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ChatMessageDeleteController
{
    public function __invoke(Request $request, ChatMemory $memory, ChatSession $session)
    {
        $request->validate([
            'conversation_id' => ['required', 'string'],
            'message_id' => ['required', 'string'],
        ]);

        $memory
            ->setAgent(current_agent())
            ->setSessionId($session->firstOrCreateChatSession())
            ->setConversationId($request->input('conversation_id'))
            ->deleteMessage($request->input('message_id'));
    }
}

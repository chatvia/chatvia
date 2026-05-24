<?php

namespace App\Http\Controllers\Chat;

use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ChatMessageDeleteController
{
    public function __invoke(Request $request, ChatMemory $memory, ChatSession $session)
    {
        $request->validate([
            'conversation_id' => ['required', 'string'],
            'message_id' => ['required', 'string'],
        ]);

        $agent = current_agent();

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateChatSession($agent))
            ->setConversationId($request->input('conversation_id'))
            ->deleteMessage($request->input('message_id'));
    }
}

<?php

namespace App\Http\Controllers\Chat\Messages;

use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\Request;

class MessageDeleteController
{
    public function __invoke(Request $request, ChatMemory $memory, TurnState $session): void
    {
        $request->validate([
            'conversation_id' => ['required', 'string'],
            'message_id' => ['required', 'string'],
        ]);

        $agent = current_agent();

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateSession($agent))
            ->setConversationId($request->input('conversation_id'))
            ->deleteMessage($request->input('message_id'));
    }
}

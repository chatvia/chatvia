<?php

namespace App\Http\Controllers\Chat\Messages;

use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Http\Request;

class MessageFeedbackController
{
    public function __invoke(Request $request, ChatMemory $memory, TurnState $session): void
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'string'],
            'message_id' => ['required', 'string'],
            'feedback' => ['nullable', 'in:positive,negative'],
        ]);

        $agent = current_agent();

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateSession($agent))
            ->setConversationId($validated['conversation_id'])
            ->feedback($validated['message_id'], $validated['feedback'] ?? null);
    }
}

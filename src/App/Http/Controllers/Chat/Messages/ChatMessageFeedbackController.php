<?php

namespace App\Http\Controllers\Chat\Messages;

use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class ChatMessageFeedbackController
{
    public function __invoke(Request $request, ChatMemory $memory, ChatSession $session): void
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'string'],
            'message_id' => ['required', 'string'],
            'feedback' => ['nullable', 'in:positive,negative'],
        ]);

        $agent = current_agent();

        $memory
            ->setAgent($agent)
            ->setExternalSessionId($session->firstOrCreateChatSession($agent))
            ->setConversationId($validated['conversation_id'])
            ->feedback($validated['message_id'], $validated['feedback'] ?? null);
    }
}

<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Http\Request;

class ConversationUpdate
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory): void
    {
        $conversation = Conversation::where('external_id', $conversationId)->firstOrFail();

        $memory
            ->setAgent(current_agent())
            ->setExternalSessionId($conversation->session->external_id)
            ->setConversationId($conversationId)
            ->updateConversationTitle($request->input('title'));
    }
}

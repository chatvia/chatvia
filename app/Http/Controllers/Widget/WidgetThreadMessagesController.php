<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatMemory;
use Illuminate\Http\Request;

class WidgetThreadMessagesController
{
    public function __invoke(Request $request, ChatMemory $memory)
    {
        $sessionId = $request->header('X-SESSION-ID');
        $threadId = $request->header('X-THREAD-ID');

        if (! $threadId) {
            return response()->json(['error' => 'Missing thread ID'], 400);
        }

        $agent = current_agent_from_widget();

        $chatMemory = $memory
            ->setAgent($agent)
            ->setSessionId($sessionId)
            ->setConversationId($threadId);

        // Check if conversation exists (handles tampered/deleted conversations)
        if (! $chatMemory->conversationExists()) {
            // Return empty messages array for non-existent conversations
            // The widget will handle this gracefully and can create a new conversation if needed
            return [];
        }

        return $chatMemory->getMessagesForConversation();
    }
}

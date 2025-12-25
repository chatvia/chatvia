<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatMemory;
use Illuminate\Http\Request;

class WidgetThreadsDeleteController
{
    public function __invoke(Request $request, ChatMemory $memory)
    {
        $sessionId = $request->header('X-SESSION-ID');

        $threadId = $request->header('X-THREAD-ID');

        $memory
            ->setAgent(current_agent_from_widget())
            ->setExternalSessionId($sessionId);

        if ($threadId) {
            // Only delete if conversation exists (handle tampered/invalid IDs gracefully)
            $chatMemory = $memory->setConversationId($threadId);
            if ($chatMemory->conversationExists()) {
                $chatMemory->deleteConversation();
            }
        } else {
            $memory->deleteAllConversations();
        }

        return response()->json(['success' => true]);
    }
}

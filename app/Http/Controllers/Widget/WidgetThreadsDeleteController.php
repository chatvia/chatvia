<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatMemory;
use Illuminate\Http\Request;

class WidgetThreadsDeleteController
{
    public function __invoke(Request $request, ChatMemory $memory)
    {
        $sessionId = $request->header('x-session-id');

        $threadId = $request->header('x-thread-id');

        $memory
            ->setAgent(current_agent_from_widget())
            ->setSessionId($sessionId);

        if ($threadId) {
            $memory->setConversationId($threadId)->deleteConversation();
        } else {
            $memory->deleteAllConversations();
        }
    }
}

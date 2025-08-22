<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatMemory;
use Illuminate\Http\Request;

class WidgetThreadMessagesController
{
    public function __invoke(Request $request, ChatMemory $memory)
    {
        $sessionId = $request->header('x-session-id');
        $threadId = $request->header('x-thread-id');

        if (! $threadId) {
            return response()->json(['error' => 'Missing thread ID'], 400);
        }

        $agent = current_agent_from_widget();

        return $memory
            ->setAgent($agent)
            ->setSessionId($sessionId)
            ->setConversationId($threadId)
            ->getMessagesForConversation();
    }
}

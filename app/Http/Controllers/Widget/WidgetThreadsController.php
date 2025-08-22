<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetThreadsController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatMemory $memory, ChatSession $session)
    {
        $agentId = $request->header('x-agent-id');
        $sessionId = $request->header('x-session-id');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return [];
        }

        $agent = current_agent_from_widget();

        $conversations = $memory
            ->setAgent($agent)
            ->setSessionId($sessionId)
            ->getConversationsForCurrentSession();

        return [
            'threads' => $conversations,
        ];
    }
}
// {
//    "threads": [
//    {
//        "id": "thread_123",
//      "title": "Chat about pricing", // Optional - will fallback to date if missing
//      "created_at": "2024-01-15T10:30:00Z", // Required for sorting
//      "updatedAt": "2024-01-15T14:20:00Z", // Optional - used for sorting if available
//      "messages": [] // Array of messages (optional)
//    },
//    {
//        "id": "thread_456",
//      "title": "Support question",
//      "created_at": "2024-01-14T09:15:00Z",
//      "updatedAt": "2024-01-14T16:45:00Z"
//    }
//  ],
//  "active_thread_id": "thread_123" // Optional - current active thread
// }

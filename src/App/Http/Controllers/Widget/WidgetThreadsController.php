<?php

namespace App\Http\Controllers\Widget;

use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;
use App\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetThreadsController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatMemory $memory, ChatSession $session)
    {
        $agentId = $request->header('X-AGENT-ID');
        $sessionId = $request->header('X-SESSION-ID');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return [];
        }

        $agent = current_agent_from_widget();

        $conversations = $memory
            ->setAgent($agent)
            ->setExternalSessionId($sessionId)
            ->getConversationsForCurrentSession();

        // If no conversations exist (tampered session, deleted conversations, etc.),
        // create a default one so the user can start chatting
        if (empty($conversations)) {
            $memory->createConversation('New Chat');
            $conversations = $memory->getConversationsForCurrentSession();
        }

        return [
            'threads' => $conversations,
        ];
    }
}
// Response format:
// {
//    "threads": [
//      {
//        "id": "thread_123",           // Required - ULID identifier
//        "title": "Chat about pricing", // Optional - defaults to "New conversation" in widget
//        "timestamp": 1704067800,      // Required - Unix timestamp for sorting
//        "visible": false              // Internal - whether thread has user messages
//      },
//      {
//        "id": "thread_456",
//        "title": "Support question",
//        "timestamp": 1703981400,
//        "visible": true
//      }
//    ]
// }

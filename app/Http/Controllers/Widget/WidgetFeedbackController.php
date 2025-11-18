<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetFeedbackController
{
    public function __invoke(Request $request, ChatMemory $memory, ChatSession $session, WidgetVerification $widgetVerification)
    {
        $agentId = $request->header('x-agent-id');
        $sessionId = $request->header('x-session-id');
        $threadId = $request->header('x-thread-id');
        $messageId = $request->header('x-message-id');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        $memory
            ->setAgent(current_agent_from_widget())
            ->setSessionId($sessionId)
            ->setConversationId($threadId)
            ->feedback($messageId, $request->boolean('feedback'));

        return response()->json(['success' => true]);
    }
}

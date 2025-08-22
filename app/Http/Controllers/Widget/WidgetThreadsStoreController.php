<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatMemory;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetThreadsStoreController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatMemory $memory)
    {
        $agentId = $request->header('x-agent-id');
        $sessionId = $request->header('x-session-id');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        return [
            'id' => $memory
                ->setAgent(current_agent_from_widget())
                ->setSessionId($sessionId)
                ->createConversation('New Chat'),
        ];
    }
}

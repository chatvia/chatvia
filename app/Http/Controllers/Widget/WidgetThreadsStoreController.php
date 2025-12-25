<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatMemory;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetThreadsStoreController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatMemory $memory)
    {
        $agentId = $request->header('X-AGENT-ID');
        $sessionId = $request->header('X-SESSION-ID');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        return [
            'id' => $memory
                ->setAgent(current_agent_from_widget())
                ->setExternalSessionId($sessionId)
                ->createConversation('New Chat'),
        ];
    }
}

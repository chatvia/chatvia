<?php

namespace App\Http\Controllers\Widget;

use App\Widget\WidgetVerification;
use Domain\Chat\Services\Memory\ChatMemory;
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

        $agent = current_agent_from_widget();

        return [
            'id' => $memory
                ->setAgent($agent)
                ->setExternalSessionId($sessionId)
                ->createConversation('New Chat'),
        ];
    }
}

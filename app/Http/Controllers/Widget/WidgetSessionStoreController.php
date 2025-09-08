<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatSession;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetSessionStoreController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatSession $session)
    {
        $agentId = $request->header('x-agent-id');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        $sessionId = $session->firstOrCreateChatSession();

        $agent = current_agent_from_widget();

        $agent
            ->chatSessions()
            ->create([
                'id' => $sessionId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

        return [
            'id' => $sessionId,
        ];
    }
}

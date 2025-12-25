<?php

namespace App\Http\Controllers\Widget;

use App\Services\Memory\ChatSession;
use App\Services\UserAgentParser;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetSessionStoreController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatSession $session)
    {
        $agentId = $request->header('X-AGENT-ID');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(data: ['error' => 'Could not verify the agent'], status: 403);
        }

        $sessionId = $session->firstOrCreateChatSession();

        $agent = current_agent_from_widget();

        $userAgentData = UserAgentParser::parse($request->userAgent());

        $agent
            ->chatSessions()
            ->create([
                'id' => $sessionId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $userAgentData['device_type'] ?? null,
                'browser' => $userAgentData['browser'] ?? null,
                'os' => $userAgentData['os'] ?? null,
            ]);

        return [
            'id' => $sessionId,
        ];
    }
}

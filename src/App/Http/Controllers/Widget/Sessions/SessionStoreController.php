<?php

namespace App\Http\Controllers\Widget\Sessions;

use App\Widget\WidgetVerification;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;

class SessionStoreController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatSession $session)
    {
        $agentId = $request->header('X-AGENT-ID');
        $sessionId = $request->header('X-SESSION-ID');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(data: ['error' => 'Could not verify the agent'], status: 403);
        }

        $agent = current_agent_from_widget();

        $userAgentData = UserAgentParser::parse($request->userAgent());

        if ($sessionId) {
            // Agent-scoped so a session id from another agent can't be
            // claimed for this one — the lookup returns null, we drop into
            // the create branch and the visitor gets a fresh id.
            $session = $widgetVerification->verifySessionExternalId($sessionId, $agent);
            if ($session) {
                return ['id' => $sessionId];
            }
        }

        $session = $agent
            ->chatSessions()
            ->create([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $userAgentData['device_type'] ?? null,
                'browser' => $userAgentData['browser'] ?? null,
                'os' => $userAgentData['os'] ?? null,
            ]);

        return [
            'id' => $session->external_id,
        ];
    }
}

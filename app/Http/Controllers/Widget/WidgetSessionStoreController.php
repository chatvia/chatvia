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

        return [
            'id' => $session->firstOrCreateChatSession(),
        ];
    }
}

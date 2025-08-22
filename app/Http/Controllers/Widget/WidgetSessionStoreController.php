<?php

namespace App\Http\Controllers\Widget;

use App\Services\Widget\WidgetSession;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetSessionStoreController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, WidgetSession $session)
    {
        $agentId = $request->header('x-agent-id');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        return [
            'id' => $session->generateSession(),
        ];
    }
}

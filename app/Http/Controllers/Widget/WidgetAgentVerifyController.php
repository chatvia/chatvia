<?php

namespace App\Http\Controllers\Widget;

use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetAgentVerifyController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification)
    {
        $agentId = $request->header('X-AGENT-ID');

        $status = $widgetVerification->verifyByAgentExternalId($agentId, $request);

        if ($status) {
            return response()->json([
                'verified' => true,
            ]);
        }

        return response()->json(['verified' => false], 403);
    }
}

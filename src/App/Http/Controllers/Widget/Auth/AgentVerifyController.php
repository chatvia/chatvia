<?php

namespace App\Http\Controllers\Widget\Auth;

use App\Widget\WidgetVerification;
use Domain\Widget\Data\WidgetAgentVerifyResultData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentVerifyController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification): JsonResponse|WidgetAgentVerifyResultData
    {
        $verified = $widgetVerification->verifyByAgentExternalId($request->header('X-AGENT-ID'), $request);

        if (! $verified) {
            return response()->json(['verified' => false], 403);
        }

        return new WidgetAgentVerifyResultData(verified: true);
    }
}

<?php

namespace App\Http\Controllers\Widget\Auth;

use Domain\Widget\Data\Auth\WidgetAgentVerifyResultData;
use Domain\Widget\Services\WidgetVerification;
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

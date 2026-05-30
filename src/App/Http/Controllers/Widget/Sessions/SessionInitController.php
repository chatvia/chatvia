<?php

namespace App\Http\Controllers\Widget\Sessions;

use App\Widget\WidgetVerification;
use Domain\Widget\Actions\InitializeWidgetSessionAction;
use Domain\Widget\Data\WidgetSessionInitResultData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionInitController
{
    public function __invoke(
        Request $request,
        WidgetVerification $widgetVerification,
        InitializeWidgetSessionAction $initialize,
    ): JsonResponse|WidgetSessionInitResultData {
        if (! $widgetVerification->verifyByAgentExternalId($request->header('X-AGENT-ID'), $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        return $initialize->execute(current_agent_from_widget(), $request);
    }
}

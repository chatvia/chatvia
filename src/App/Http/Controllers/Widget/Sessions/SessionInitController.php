<?php

namespace App\Http\Controllers\Widget\Sessions;

use App\Widget\WidgetVerification;
use Domain\Widget\Actions\InitializeWidgetSessionAction;
use Domain\Widget\Data\Session\WidgetSessionInitData;
use Domain\Widget\Data\Session\WidgetSessionInitResultData;
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

        $data = new WidgetSessionInitData(
            existingSessionId: $request->header('X-SESSION-ID'),
            existingThreadId: $request->header('X-THREAD-ID'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            acceptLanguage: $request->header('Accept-Language'),
            referrerUrl: $request->header('Referer'),
            pageUrl: $request->input('page_url'),
        );

        return $initialize->execute(current_agent_from_widget(), $data);
    }
}

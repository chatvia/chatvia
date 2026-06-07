<?php

namespace App\Http\Controllers\Widget\Sessions;

use Domain\Widget\Actions\InitializeWidgetSessionAction;
use Domain\Widget\Data\Session\WidgetSessionInitData;
use Domain\Widget\Data\Session\WidgetSessionInitResultData;
use Domain\Widget\Services\WidgetVerification;
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
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $data = new WidgetSessionInitData(
            existingSessionId: $request->header('X-SESSION-ID'),
            existingConversationId: $request->header('X-CONVERSATION-ID'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            acceptLanguage: $request->header('Accept-Language'),
            referrerUrl: $request->header('Referer'),
            pageUrl: $request->input('page_url'),
        );

        // On a protected agent EnsureWidgetIsAccessible attached the resolved
        // chatter; bind the (new or resolved) session to them. Null for public
        // agents (anonymous visitors), leaving their session unscoped as before.
        return $initialize->execute(
            current_agent_from_widget(),
            $data,
            $request->attributes->get('widget_user'),
        );
    }
}

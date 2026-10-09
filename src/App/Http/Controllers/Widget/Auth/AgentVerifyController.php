<?php

namespace App\Http\Controllers\Widget\Auth;

use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Widget\Data\Auth\WidgetAgentVerifyResultData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * First call the widget makes: is this agent allowed to run on this page?
 * It is the embed-origin check itself, so it answers `{verified: bool}`
 * instead of sitting behind VerifyWidgetOrigin.
 */
class AgentVerifyController
{
    public function __invoke(
        Request $request,
        #[WidgetAgent] ?Agent $agent,
        WidgetVerification $widgetVerification,
    ): JsonResponse|WidgetAgentVerifyResultData {
        if (! $agent instanceof Agent || ! $widgetVerification->isRequestFromAllowedSite($agent, $request)) {
            return response()->json(new WidgetAgentVerifyResultData(verified: false), JsonResponse::HTTP_FORBIDDEN);
        }

        return new WidgetAgentVerifyResultData(verified: true);
    }
}

<?php

namespace App\Http\Controllers\Widget\Settings;

use Domain\Widget\Actions\BuildWidgetSettingsAction;
use Domain\Widget\Data\Settings\WidgetSettingsResourceData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetSettingsController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, BuildWidgetSettingsAction $build): JsonResponse|WidgetSettingsResourceData
    {
        if (! $widgetVerification->verifyByAgentExternalId($request->header('X-AGENT-ID'), $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        return $build->execute(current_agent_from_widget(), $request->header('Accept-Language'));
    }
}

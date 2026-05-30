<?php

namespace App\Http\Controllers\Widget\Settings;

use App\Widget\WidgetVerification;
use Domain\Widget\Actions\BuildWidgetSettingsAction;
use Domain\Widget\Data\WidgetSettingsResourceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetSettingsController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, BuildWidgetSettingsAction $build): JsonResponse|WidgetSettingsResourceData
    {
        if (! $widgetVerification->verifyByAgentExternalId($request->header('X-AGENT-ID'), $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        return $build->execute(current_agent_from_widget());
    }
}

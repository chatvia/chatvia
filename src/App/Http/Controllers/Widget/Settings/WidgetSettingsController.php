<?php

namespace App\Http\Controllers\Widget\Settings;

use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Widget\Actions\BuildWidgetSettingsAction;
use Domain\Widget\Data\Settings\WidgetSettingsData;
use Illuminate\Http\Request;

/**
 * Appearance and behaviour settings the widget loads at boot, with the agent's
 * texts resolved for the visitor's browser language.
 */
class WidgetSettingsController
{
    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        BuildWidgetSettingsAction $build,
    ): WidgetSettingsData {
        return $build->execute($agent, $request->header('Accept-Language'));
    }
}

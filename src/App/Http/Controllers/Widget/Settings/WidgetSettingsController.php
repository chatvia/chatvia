<?php

namespace App\Http\Controllers\Widget\Settings;

use Domain\Widget\Actions\BuildWidgetSettingsAction;
use Domain\Widget\Data\Settings\WidgetSettingsResourceData;
use Illuminate\Http\Request;

class WidgetSettingsController
{
    public function __invoke(Request $request, BuildWidgetSettingsAction $build): WidgetSettingsResourceData
    {
        return $build->execute(current_agent_from_widget(), $request->header('Accept-Language'));
    }
}

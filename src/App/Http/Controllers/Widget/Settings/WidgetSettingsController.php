<?php

namespace App\Http\Controllers\Widget\Settings;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use Domain\Widget\Actions\BuildWidgetSettingsAction;
use Domain\Widget\Data\Settings\WidgetSettingsData;
use Illuminate\Http\Request;

/**
 * Appearance and behaviour settings the widget loads at boot, with the agent's
 * texts resolved for the visitor's browser language.
 */
class WidgetSettingsController
{
    use InteractsWithWidgetRequest;

    public function __invoke(Request $request, BuildWidgetSettingsAction $build): WidgetSettingsData
    {
        return $build->execute($this->widgetAgent(), $request->header('Accept-Language'));
    }
}

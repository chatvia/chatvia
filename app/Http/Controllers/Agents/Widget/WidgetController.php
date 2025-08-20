<?php

namespace App\Http\Controllers\Agents\Widget;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WidgetController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $widgetSettings = $agent->settings('widget');

        return Inertia::render('agents/Widget', [
            'agent' => $agent,
            'widgetSettings' => $widgetSettings,
            'embedScriptUrl' => config('widget.script_url_embed'),
        ]);
    }
}

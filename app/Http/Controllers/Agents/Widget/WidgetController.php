<?php

namespace App\Http\Controllers\Agents\Widget;

use App\Features\Widget as WidgetFeature;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Pennant\Feature;

class WidgetController
{
    public function __invoke(Request $request, Agent $agent)
    {
        if (! Feature::active(WidgetFeature::class)) {
            return back()->with('error', 'Widget feature is not enabled');
        }

        $widgetSettings = $agent->settings('widget');

        return Inertia::render('agents/Widget', [
            'agent' => $agent,
            'widgetSettings' => $widgetSettings,
            'embedScriptUrl' => config('widget.script_url_embed'),
        ]);
    }
}

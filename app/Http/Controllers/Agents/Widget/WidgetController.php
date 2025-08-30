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

        if (!$agent->isWidget()) {
            return back()->with('error', 'This agent does not support the widget feature');
        }

        return Inertia::render('agents/Widget', [
            'agent' => $agent,
            'widgetSettings' => $agent->settings('widget'),
            'appearanceSettings' => $agent->settings('appearance'),
            'embedScriptUrl' => config('widget.script_url_embed'),
        ]);
    }
}

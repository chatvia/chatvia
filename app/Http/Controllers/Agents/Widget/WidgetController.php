<?php

namespace App\Http\Controllers\Agents\Widget;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WidgetController
{
    public function __invoke(Request $request, Agent $agent)
    {
        if (! $agent->isWidget()) {
            return back()->with('error', 'This agent does not support the widget feature');
        }

        return Inertia::render('agents/Widget', [
            'agent' => $agent,
            'widgetSettings' => $agent->settings('widget'),
            'appearanceSettings' => $agent->settings('appearance'),
            'embedScriptUrl' => config('widget.script_url_embed'),
            'starters' => $agent->starters->toArray(),
        ]);
    }
}

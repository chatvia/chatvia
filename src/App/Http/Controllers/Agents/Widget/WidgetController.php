<?php

namespace App\Http\Controllers\Agents\Widget;

use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
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
            'agent' => AgentResourceData::fromModel($agent),
            'widgetSettings' => $agent->settings('widget'),
            'appearanceSettings' => $agent->settings('appearance'),
            'embedScriptUrl' => config('widget.script_url_embed'),
            'availableLanguages' => config('widget.available_languages'),
            'starters' => $agent->starters->toArray(),
            'has_permission' => has_permission_to_agent($agent, 'agent.setup'),
        ]);
    }
}

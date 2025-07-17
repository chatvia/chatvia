<?php

namespace App\Http\Controllers\Agents\Appearance;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentAppearanceController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Appearance', [
            'agent' => $agent,
            'settings' => $agent->settings('appearance'),
            'default_settings' => config('model-settings.defaults.App\Models\Agent.appearance'),
        ]);
    }
}

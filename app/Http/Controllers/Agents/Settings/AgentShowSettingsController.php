<?php

namespace App\Http\Controllers\Agents\Settings;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentShowSettingsController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Settings', ['agent' => $agent]);
    }
}

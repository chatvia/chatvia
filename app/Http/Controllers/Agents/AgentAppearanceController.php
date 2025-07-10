<?php

namespace App\Http\Controllers\Agents;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentAppearanceController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Appearance', ['agent' => $agent]);
    }
}

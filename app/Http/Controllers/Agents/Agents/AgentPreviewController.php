<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentPreviewController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Preview', [
            'agent' => $agent,
        ]);
    }
}

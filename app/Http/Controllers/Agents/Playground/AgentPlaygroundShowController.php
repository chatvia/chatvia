<?php

namespace App\Http\Controllers\Agents\Playground;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentPlaygroundShowController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Playground', [
            'agent' => $agent,
        ]);
    }
}

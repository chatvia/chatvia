<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentUpdateActivityController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $agent->update(['is_active' => $request->input('is_active')]);
    }
}

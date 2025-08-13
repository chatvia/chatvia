<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentUpdateActivityController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $agent->update(['is_active' => $request->input('is_active')]);
    }
}

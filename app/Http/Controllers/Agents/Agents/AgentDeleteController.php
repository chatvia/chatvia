<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentDeleteController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('delete', $agent),
            code: 404,
        );

        $agent->delete();

        return redirect()->route('agents.index');
    }
}

<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentUpdateController
{
    public function __invoke(Agent $agent, Request $request)
    {
        //        $request->validate([
        //            ''
        //        ]);

        $agent->update($request->all());
    }
}

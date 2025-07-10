<?php

namespace App\Http\Controllers\Agents;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentDeleteController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $agent->delete();
    }
}

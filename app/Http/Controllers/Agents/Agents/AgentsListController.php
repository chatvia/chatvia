<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Data\Resources\Agent\AgentResourceData;
use Illuminate\Http\Request;

class AgentsListController
{
    public function __invoke(Request $request)
    {
        $agents = $request
            ->user()
            ->agents()
            ->withStats()
            ->latest()
            ->get();

        return AgentResourceData::collection($agents);
    }
}

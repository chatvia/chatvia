<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Data\Resources\Agent\AgentResourceData;
use Illuminate\Http\Request;

class AgentsListController
{
    public function __invoke(Request $request)
    {
        $team = current_team();

        if (! $team) {
            return AgentResourceData::collection(collect());
        }

        $agents = $team
            ->agents()
            ->withStats()
            ->latest()
            ->get();

        return AgentResourceData::collection($agents);
    }
}

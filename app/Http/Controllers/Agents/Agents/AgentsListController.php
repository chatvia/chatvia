<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Data\Resources\Agent\AgentResourceData;
use App\Services\Teams\TeamService;
use Illuminate\Http\Request;

class AgentsListController
{
    public function __construct(
        private TeamService $teamService
    ) {}

    public function __invoke(Request $request)
    {
        $team = current_team();
        $user = $request->user();

        if (! $team) {
            return AgentResourceData::collection(collect());
        }

        // Get only agents the user has access to
        $accessibleAgentIds = $this->teamService->accessibleAgents($user, $team)->pluck('id');

        $agents = $team
            ->agents()
            ->whereIn('id', $accessibleAgentIds)
            ->withStats()
            ->latest()
            ->get();

        return AgentResourceData::collection($agents);
    }
}

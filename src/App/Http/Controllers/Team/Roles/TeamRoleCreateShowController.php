<?php

namespace App\Http\Controllers\Team\Roles;

use App\Team\Data\Resources\Team\TeamData;
use App\Team\Models\Team;
use App\Team\Services\TeamService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamRoleCreateShowController
{
    public function __construct(
        private TeamService $teamService
    ) {}

    public function __invoke(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can create roles.');
        }

        return Inertia::render('teams/roles/Create', [
            'team' => TeamData::fromModel($team),
            'agents' => $this->teamService->teamAgents($team),
            'permissionsConfig' => $this->teamService->permissionsConfig(),
            'permissionGroups' => $this->teamService->permissionGroups(),
            'permissionScopes' => $this->teamService->permissionScopes(),
        ]);
    }
}

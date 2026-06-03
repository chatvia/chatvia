<?php

namespace App\Http\Controllers\Team\Roles;

use Domain\Membership\Team\Data\Resources\Roles\TeamRoleData;
use Domain\Membership\Team\Data\Resources\Team\TeamData;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamRoleEditShowController
{
    public function __construct(
        private TeamService $teamService
    ) {}

    public function __invoke(Request $request, Team $team, int $roleId)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can edit roles.');
        }

        $roleModel = $team->roles()->with('permissions')->findOrFail($roleId);

        if ($roleModel->code === 'owner') {
            return redirect()->route('teams.show', $team)->with('error', 'Cannot edit the owner role.');
        }

        return Inertia::render('teams/roles/Edit', [
            'team' => TeamData::fromModel($team),
            'role' => TeamRoleData::fromModel($roleModel),
            'agents' => $this->teamService->teamAgents($team),
            'permissionsConfig' => $this->teamService->permissionsConfig(),
            'permissionGroups' => $this->teamService->permissionGroups(),
            'permissionScopes' => $this->teamService->permissionScopes(),
        ]);
    }
}

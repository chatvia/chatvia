<?php

namespace App\Http\Controllers\Team\Roles;

use Domain\Membership\Team\Data\Resources\Roles\TeamRoleData;
use Domain\Membership\Team\Data\Resources\Team\TeamAgentData;
use Domain\Membership\Team\Data\Resources\Team\TeamData;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamPermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The role editor for an existing custom role. Only the owner may edit roles,
 * and the owner role itself can never be edited.
 */
class TeamRoleEditShowController
{
    public function __construct(
        private readonly TeamPermissionCatalog $catalog,
    ) {}

    public function __invoke(Request $request, Team $team, Role $role): Response|RedirectResponse
    {
        abort_if($request->user()->cannot('update', $team), 403, __('Only the team owner can edit roles.'));

        $role->load('permissions');

        if ($role->isOwnerRole()) {
            return redirect()->route('teams.show', $team)->with('error', __('Cannot edit the owner role.'));
        }

        return Inertia::render('dashboard/teams/pages/roles/Edit', [
            'team' => TeamData::fromModel($team),
            'role' => TeamRoleData::fromModel($role),
            'agents' => TeamAgentData::collectForTeam($team),
            'permissionsConfig' => $this->catalog->permissions(),
            'permissionGroups' => $this->catalog->groups(),
            'permissionScopes' => $this->catalog->scopes(),
        ]);
    }
}

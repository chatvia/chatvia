<?php

namespace App\Http\Controllers\Team\Roles;

use Domain\Membership\Team\Data\Resources\Team\TeamAgentData;
use Domain\Membership\Team\Data\Resources\Team\TeamData;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamPermissionCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "new role" editor: the team's agents plus the permission catalog.
 * Only the owner may create roles.
 */
class TeamRoleCreateShowController
{
    public function __construct(
        private readonly TeamPermissionCatalog $catalog,
    ) {}

    public function __invoke(Request $request, Team $team): Response
    {
        abort_if($request->user()->cannot('update', $team), 403, __('Only the team owner can create roles.'));

        return Inertia::render('teams/roles/Create', [
            'team' => TeamData::fromModel($team),
            'agents' => TeamAgentData::collectForTeam($team),
            'permissionsConfig' => $this->catalog->permissions(),
            'permissionGroups' => $this->catalog->groups(),
            'permissionScopes' => $this->catalog->scopes(),
        ]);
    }
}

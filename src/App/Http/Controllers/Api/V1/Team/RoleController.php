<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Requests\Api\V1\Team\SaveApiTeamRoleRequest;
use App\Http\Resources\Api\V1\Team\RoleData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Actions\Roles\UpdateTeamRoleAction;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\JsonResponse;

/**
 * Roles live inside a team. A role grants access to a set of agents — all of
 * them or a specific list — and carries the dashboard permissions its members
 * hold. After creating a role, assign users to it via
 * `POST /api/v1/teams/{team}/roles/{role}/users`. A role is looked up inside
 * the team named in the URL, so another team's role is a 404.
 */
#[Group(name: 'Teams', weight: 40)]
class RoleController
{
    /**
     * Create role
     */
    public function store(SaveApiTeamRoleRequest $request, Team $team, CreateTeamRoleAction $action): JsonResponse
    {
        return response()->json(['data' => RoleData::fromModel($action->execute($request->toData($team)))], 201);
    }

    /**
     * Update role
     *
     * Replaces the role's name, agent access, and permissions with the values
     * supplied.
     *
     * @return array{data: RoleData}
     */
    public function update(SaveApiTeamRoleRequest $request, Team $team, Role $role, UpdateTeamRoleAction $action): array
    {
        return ['data' => RoleData::fromModel($action->execute($role, $request->toData($team)))];
    }
}

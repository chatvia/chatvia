<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Requests\Api\V1\Team\AssignRoleMemberRequest;
use App\Http\Resources\Api\V1\Auth\UserData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Membership\Team\Actions\Members\AssignUserToRoleAction;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\JsonResponse;

/**
 * Assign your users to a team role. The user joins the team under that role and
 * takes on its agent access and permissions. A user holds one role per team —
 * assigning again moves them to the new role.
 */
#[Group(name: 'Teams', weight: 40)]
class RoleMemberController
{
    /**
     * Assign a teammate to a role
     *
     * Body: `{ user_id: string }` — a user's `id` from `POST /api/v1/users`.
     */
    public function store(AssignRoleMemberRequest $request, Team $team, Role $role, AssignUserToRoleAction $action): JsonResponse
    {
        abort_unless($role->team_id === $team->id, 404);

        $user = $request->member();

        $action->execute($team, $role, $user);

        return response()->json(['data' => UserData::fromModel($user)], 201);
    }
}

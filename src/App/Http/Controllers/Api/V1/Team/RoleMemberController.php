<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Resources\Api\V1\Auth\UserData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Membership\Team\Actions\Members\AssignUserToRoleAction;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\Request;

/**
 * Assign your users to a team role. The user joins the team under that role and
 * takes on its agent access and permissions. A user holds one role per team —
 * assigning again moves them to the new role.
 */
#[Group(name: 'Teams', weight: 40)]
class RoleMemberController
{
    /**
     * Assign user to role
     *
     * Body: `{ user_id: string }` — a user's `id` from `POST /api/v1/users`.
     *
     * @return array{data: UserData}
     */
    public function store(Request $request, Team $team, Role $role, AssignUserToRoleAction $action): array
    {
        $owner = $request->user()->workspace();

        // Same gate as the web side (UpdateMemberRoleRequest): TeamPolicy::updateTeamMember.
        abort_unless($request->user()->can('updateTeamMember', $team), 403);
        abort_unless($role->team_id === $team->id, 404);

        $validated = $request->validate(['user_id' => ['required', 'string']]);

        /** @var User $user */
        $user = User::query()
            ->where('external_id', $validated['user_id'])
            ->where('workspace_id', $owner->id)
            ->firstOrFail();

        $action->execute($team, $role, $user);

        return ['data' => UserData::fromModel($user)];
    }
}

<?php

namespace Domain\Membership\Team\Actions\Members;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;

class AssignUserToRoleAction
{
    /**
     * Attach the user to the team under the given role, or move them to it if
     * they are already a member (one role per user per team).
     */
    public function execute(Team $team, Role $role, User $user): void
    {
        if ($team->members()->where('users.id', $user->id)->exists()) {
            $team->members()->updateExistingPivot($user->id, ['role_id' => $role->id]);

            return;
        }

        $team->members()->attach($user->id, ['role_id' => $role->id]);
    }
}

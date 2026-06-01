<?php

namespace Domain\Teams\Actions\Members;

use App\Auth\Models\User;
use App\Teams\Models\Role;
use App\Teams\Models\Team;

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

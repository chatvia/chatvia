<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Actions\Members;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;

/**
 * Puts a user on a team under a role (public API). A user holds one role per
 * team, so assigning a member again moves them to the new role.
 */
class AssignUserToRoleAction
{
    public function execute(Team $team, Role $role, User $user): void
    {
        if ($team->members()->where('users.id', $user->id)->exists()) {
            $team->members()->updateExistingPivot($user->id, ['role_id' => $role->id]);

            return;
        }

        $team->members()->attach($user->id, ['role_id' => $role->id]);
    }
}

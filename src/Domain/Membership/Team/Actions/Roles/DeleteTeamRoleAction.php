<?php

namespace Domain\Membership\Team\Actions\Roles;

use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Support\Exceptions\DomainValidationException;

/**
 * Deletes a custom team role. Refused while any member still holds the role,
 * so nobody is left on the team without one.
 */
class DeleteTeamRoleAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Team $team, Role $role): void
    {
        $membersWithRole = $team->members()->wherePivot('role_id', $role->id)->count();

        if ($membersWithRole > 0) {
            throw new DomainValidationException('role', __('Cannot delete this role. :count member(s) are assigned to it.', ['count' => $membersWithRole]));
        }

        $role->delete();
    }
}

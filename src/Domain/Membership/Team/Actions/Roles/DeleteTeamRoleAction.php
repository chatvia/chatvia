<?php

namespace Domain\Membership\Team\Actions\Roles;

use Support\Exceptions\DomainValidationException;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;

class DeleteTeamRoleAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Team $team, Role $role): void
    {
        $membersWithRole = $team->members()->wherePivot('role_id', $role->id)->count();

        if ($membersWithRole > 0) {
            throw new DomainValidationException('role', 'Cannot delete this role. '.$membersWithRole.' member(s) are assigned to it.');
        }

        $role->delete();
    }
}

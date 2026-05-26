<?php

namespace Domain\Teams\Actions;

use App\Teams\Models\Role;
use App\Teams\Models\Team;
use Domain\Teams\Exceptions\InviteRejectedException;

class DeleteTeamRoleAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(Team $team, Role $role): void
    {
        $membersWithRole = $team->members()->wherePivot('role_id', $role->id)->count();

        if ($membersWithRole > 0) {
            throw new InviteRejectedException('role', 'Cannot delete this role. '.$membersWithRole.' member(s) are assigned to it.');
        }

        $role->delete();
    }
}

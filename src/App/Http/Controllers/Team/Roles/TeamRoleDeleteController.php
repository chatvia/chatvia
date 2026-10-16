<?php

namespace App\Http\Controllers\Team\Roles;

use Domain\Membership\Team\Actions\Roles\DeleteTeamRoleAction;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Deletes a custom team role. Only the owner may; the owner and admin roles
 * are protected. A role that members still hold is refused by the action, and
 * the global exception mapping turns that into a `role` field error.
 */
class TeamRoleDeleteController
{
    public function __invoke(
        Request $request,
        Team $team,
        Role $role,
        DeleteTeamRoleAction $delete,
    ): RedirectResponse {
        abort_if($request->user()->cannot('update', $team), 403, __('Only the team owner can delete roles.'));

        abort_if($role->isProtected(), 403, __('Cannot delete the owner role.'));

        $delete->execute($team, $role);

        return back()->with('success', __('Role deleted successfully.'));
    }
}

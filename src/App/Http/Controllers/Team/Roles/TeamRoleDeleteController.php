<?php

namespace App\Http\Controllers\Team\Roles;

use Domain\Membership\Team\Actions\Roles\DeleteTeamRoleAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Support\Exceptions\DomainValidationException;

/**
 * Deletes a custom team role. Only the owner may; the owner and admin roles
 * are protected, and a role that members still hold is refused with a flash.
 */
class TeamRoleDeleteController
{
    public function __invoke(
        Request $request,
        Team $team,
        int $roleId,
        DeleteTeamRoleAction $delete,
    ): RedirectResponse {
        abort_if($request->user()->cannot('update', $team), 403, __('Only the team owner can delete roles.'));

        $role = $team->roles()->findOrFail($roleId);

        abort_if($role->isProtected(), 403, __('Cannot delete the owner or admin role.'));

        try {
            $delete->execute($team, $role);
        } catch (DomainValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Role deleted successfully.'));
    }
}

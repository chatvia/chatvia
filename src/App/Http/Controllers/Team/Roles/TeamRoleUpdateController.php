<?php

namespace App\Http\Controllers\Team\Roles;

use App\Http\Requests\Team\Roles\SaveTeamRoleRequest;
use Domain\Membership\Team\Actions\Roles\UpdateTeamRoleAction;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;

/**
 * Saves changes to a custom team role. The owner role can never be modified.
 */
class TeamRoleUpdateController
{
    public function __invoke(
        SaveTeamRoleRequest $request,
        Team $team,
        Role $role,
        UpdateTeamRoleAction $updateRole,
    ): RedirectResponse {
        abort_if($role->isOwnerRole(), 403, __('Cannot modify the owner role.'));

        $updateRole->execute($role, $request->toData($team));

        return redirect()->route('teams.show', $team)->with('success', __('Role updated successfully.'));
    }
}

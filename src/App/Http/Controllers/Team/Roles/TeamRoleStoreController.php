<?php

namespace App\Http\Controllers\Team\Roles;

use App\Http\Requests\Team\Roles\SaveTeamRoleRequest;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;

/**
 * Saves a new custom team role from the role editor.
 */
class TeamRoleStoreController
{
    public function __invoke(
        SaveTeamRoleRequest $request,
        Team $team,
        CreateTeamRoleAction $createRole,
    ): RedirectResponse {
        $createRole->execute($request->toData($team));

        return redirect()->route('teams.show', $team)->with('success', __('Role created successfully.'));
    }
}

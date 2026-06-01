<?php

namespace App\Http\Controllers\Teams\Roles;

use App\Http\Requests\Teams\Roles\SaveTeamRoleRequest;
use App\Teams\Models\Team;
use Domain\Teams\Actions\Roles\CreateTeamRoleAction;
use Domain\Teams\Exceptions\InviteRejectedException;
use Illuminate\Http\RedirectResponse;

class TeamRoleStoreController
{
    public function __invoke(
        SaveTeamRoleRequest $request,
        Team $team,
        CreateTeamRoleAction $createRole,
    ): RedirectResponse {
        try {
            $createRole->execute($request->toData($team));
        } catch (InviteRejectedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('teams.show', $team)->with('success', 'Role created successfully.');
    }
}

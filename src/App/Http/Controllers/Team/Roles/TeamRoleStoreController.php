<?php

namespace App\Http\Controllers\Team\Roles;

use App\Http\Requests\Team\Roles\SaveTeamRoleRequest;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Support\Exceptions\DomainValidationException;

class TeamRoleStoreController
{
    public function __invoke(
        SaveTeamRoleRequest $request,
        Team $team,
        CreateTeamRoleAction $createRole,
    ): RedirectResponse {
        try {
            $createRole->execute($request->toData($team));
        } catch (DomainValidationException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('teams.show', $team)->with('success', 'Role created successfully.');
    }
}

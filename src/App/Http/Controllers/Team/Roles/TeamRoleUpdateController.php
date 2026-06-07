<?php

namespace App\Http\Controllers\Team\Roles;

use App\Http\Requests\Team\Roles\SaveTeamRoleRequest;
use Domain\Membership\Team\Actions\Roles\UpdateTeamRoleAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Support\Exceptions\DomainValidationException;

class TeamRoleUpdateController
{
    public function __invoke(
        SaveTeamRoleRequest $request,
        Team $team,
        int $roleId,
        UpdateTeamRoleAction $updateRole,
    ): RedirectResponse {
        $roleModel = $team->roles()->findOrFail($roleId);

        if ($roleModel->code === 'owner') {
            abort(403, 'Cannot modify the owner role.');
        }

        try {
            $updateRole->execute($roleModel, $request->toData($team));
        } catch (DomainValidationException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('teams.show', $team)->with('success', 'Role updated successfully.');
    }
}

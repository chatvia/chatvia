<?php

namespace App\Http\Controllers\Teams\Roles;

use App\Http\Requests\Teams\Roles\SaveTeamRoleRequest;
use App\Teams\Models\Team;
use Domain\Teams\Actions\UpdateTeamRoleAction;
use Domain\Teams\Exceptions\InviteRejectedException;
use Illuminate\Http\RedirectResponse;

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
        } catch (InviteRejectedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('teams.show', $team)->with('success', 'Role updated successfully.');
    }
}

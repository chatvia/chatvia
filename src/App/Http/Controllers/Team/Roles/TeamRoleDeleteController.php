<?php

namespace App\Http\Controllers\Team\Roles;

use App\Team\Models\Team;
use Domain\Team\Actions\Roles\DeleteTeamRoleAction;
use Domain\Team\Exceptions\InviteRejectedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamRoleDeleteController
{
    public function __invoke(
        Request $request,
        Team $team,
        int $roleId,
        DeleteTeamRoleAction $delete,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can delete roles.');
        }

        $roleModel = $team->roles()->findOrFail($roleId);

        if (in_array($roleModel->code, ['owner', 'admin'])) {
            abort(403, 'Cannot delete the owner or admin role.');
        }

        try {
            $delete->execute($team, $roleModel);
        } catch (InviteRejectedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Role deleted successfully.');
    }
}

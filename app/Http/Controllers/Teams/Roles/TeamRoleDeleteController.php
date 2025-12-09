<?php

namespace App\Http\Controllers\Teams\Roles;

use App\Models\Team;
use Illuminate\Http\Request;

class TeamRoleDeleteController
{
    public function __invoke(Request $request, Team $team, int $roleId)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can delete roles.');
        }

        $roleModel = $team->roles()->findOrFail($roleId);

        if (in_array($roleModel->code, ['owner', 'admin'])) {
            abort(403, 'Cannot delete the owner or admin role.');
        }

        $membersWithRole = $team->members()->wherePivot('role_id', $roleModel->id)->count();
        if ($membersWithRole > 0) {
            return back()->with('error', 'Cannot delete this role. '.$membersWithRole.' member(s) are assigned to it.');
        }

        $roleModel->delete();

        return back()->with('success', 'Role deleted successfully.');
    }
}

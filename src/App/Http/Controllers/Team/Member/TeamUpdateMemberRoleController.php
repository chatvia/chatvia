<?php

namespace App\Http\Controllers\Team\Member;

use App\Http\Requests\Team\UpdateMemberRoleRequest;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\UpdateTeamMemberRoleAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;

class TeamUpdateMemberRoleController
{
    public function __invoke(
        UpdateMemberRoleRequest $request,
        Team $team,
        User $member,
        UpdateTeamMemberRoleAction $updateRole,
    ): RedirectResponse {
        if ($team->user_id === $member->id) {
            abort(403, 'Cannot change the owner\'s role.');
        }

        $updateRole->execute($team, $member, $request->string('role')->toString());

        return back()->with('success', 'Member role updated successfully.');
    }
}

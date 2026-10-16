<?php

namespace App\Http\Controllers\Team\Member;

use App\Http\Requests\Team\UpdateMemberRoleRequest;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\UpdateTeamMemberRoleAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;

/**
 * Moves a team member to another role. The owner's own role never changes
 * (the Action refuses).
 */
class TeamUpdateMemberRoleController
{
    public function __invoke(UpdateMemberRoleRequest $request, Team $team, User $member, UpdateTeamMemberRoleAction $updateRole): RedirectResponse
    {
        $data = $request->toData($team, $member);

        $updateRole->execute($data->team, $data->member, $data->roleCode);

        return back()->with('success', __('Member role updated successfully.'));
    }
}

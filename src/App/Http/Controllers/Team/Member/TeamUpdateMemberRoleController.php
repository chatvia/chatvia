<?php

namespace App\Http\Controllers\Team\Member;

use App\Http\Requests\Team\UpdateMemberRoleRequest;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;

/**
 * Moves a team member to another role. The owner's own role never changes.
 */
class TeamUpdateMemberRoleController
{
    public function __invoke(UpdateMemberRoleRequest $request, Team $team, User $member): RedirectResponse
    {
        abort_if($member->ownsTeam($team), 403, __("Cannot change the owner's role."));

        $data = $request->toData($team, $member);

        $data->team->updateMember($data->member, $data->roleCode);

        return back()->with('success', __('Member role updated successfully.'));
    }
}

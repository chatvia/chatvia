<?php

namespace App\Http\Controllers\Team\Member;

use App\Identity\Workspace\Actions\Account\DeleteEmployeeAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Permanently delete a teammate's account and all their data. Distinct from
 * {@see TeamRemoveMemberController} (`teams.remove-member`), which only detaches
 * the `team_user` pivot. The route sits behind DenyIfTeammate, so only an
 * owner / admin reaches here; we still confirm the target is one of *this*
 * workspace's employees and answer 404 (not 403) otherwise, so the endpoint
 * never confirms a stranger's user id.
 */
class RemoveTeammateFromWorkspaceController
{
    public function __invoke(Request $request, User $member, DeleteEmployeeAction $deleteEmployee): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($member->isTeammate() && $member->workspace()->id === $actor->workspace()->id, 404);

        $name = $member->name;

        $deleteEmployee->execute($member);

        return back()->with('success', __(":name's account and all their data were deleted.", ['name' => $name]));
    }
}

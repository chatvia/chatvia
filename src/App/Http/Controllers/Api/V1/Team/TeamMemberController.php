<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Team;

use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\Response;

/**
 * Removes a teammate from a team (the account stays in the workspace). The
 * API counterpart of the dashboard's member removal; a member of another
 * workspace is a 404 (workspace scope), and one not on this team too.
 */
#[Group(name: 'Teams', weight: 40)]
class TeamMemberController
{
    /**
     * Remove team member
     *
     * Detaches the user from the team; their account is kept. The team owner
     * cannot be removed.
     */
    public function destroy(Team $team, User $member): Response
    {
        // `{member}` is resolved through $team->members(), so anyone not on
        // the team — the owner included, who holds the team rather than a
        // membership — is already a 404. This guard only backstops data drift.
        abort_if($member->ownsTeam($team), 403, __('Cannot remove the team owner.'));

        $team->removeMember($member);

        return response()->noContent();
    }
}

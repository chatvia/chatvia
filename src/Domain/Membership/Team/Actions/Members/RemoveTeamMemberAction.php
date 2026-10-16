<?php

namespace Domain\Membership\Team\Actions\Members;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Exceptions\TeamOwnerImmutableException;
use Domain\Membership\Team\Models\Team;

/**
 * Removes a member from a team (detaches the pivot; the account stays) —
 * whether the owner removes them or they leave themselves. The owner holds
 * the team rather than a membership and can never be removed; shared by the
 * dashboard and the public API so the rule cannot drift.
 */
class RemoveTeamMemberAction
{
    /**
     * @throws TeamOwnerImmutableException when the member is the team's owner
     */
    public function execute(Team $team, User $member): void
    {
        if ($member->ownsTeam($team)) {
            throw TeamOwnerImmutableException::forRemoval();
        }

        $team->removeMember($member);
    }
}

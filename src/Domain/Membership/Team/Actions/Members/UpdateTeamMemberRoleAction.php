<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Actions\Members;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Exceptions\TeamOwnerImmutableException;
use Domain\Membership\Team\Models\Team;

/**
 * Moves a team member to another of the team's roles. The owner holds every
 * permission without a role row, so their "role" never changes.
 */
class UpdateTeamMemberRoleAction
{
    /**
     * @throws TeamOwnerImmutableException when the member is the team's owner
     */
    public function execute(Team $team, User $member, string $roleCode): void
    {
        if ($member->ownsTeam($team)) {
            throw TeamOwnerImmutableException::forRoleChange();
        }

        $team->updateMember($member, $roleCode);
    }
}

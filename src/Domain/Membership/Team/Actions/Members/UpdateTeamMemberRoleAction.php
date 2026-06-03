<?php

namespace Domain\Membership\Team\Actions\Members;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;

class UpdateTeamMemberRoleAction
{
    public function execute(Team $team, User $member, string $roleCode): void
    {
        $team->updateMember($member, $roleCode);
    }
}

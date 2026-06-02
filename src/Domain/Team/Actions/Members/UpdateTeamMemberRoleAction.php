<?php

namespace Domain\Team\Actions\Members;

use App\Workspace\Models\User;
use App\Team\Models\Team;

class UpdateTeamMemberRoleAction
{
    public function execute(Team $team, User $member, string $roleCode): void
    {
        $team->updateMember($member, $roleCode);
    }
}

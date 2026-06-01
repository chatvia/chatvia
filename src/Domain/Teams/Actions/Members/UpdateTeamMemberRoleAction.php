<?php

namespace Domain\Teams\Actions\Members;

use App\Auth\Models\User;
use App\Teams\Models\Team;

class UpdateTeamMemberRoleAction
{
    public function execute(Team $team, User $member, string $roleCode): void
    {
        $team->updateMember($member, $roleCode);
    }
}

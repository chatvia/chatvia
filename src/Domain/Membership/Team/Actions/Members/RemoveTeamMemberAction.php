<?php

namespace Domain\Membership\Team\Actions\Members;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;

class RemoveTeamMemberAction
{
    public function execute(Team $team, User $member): void
    {
        $team->removeMember($member);
    }
}

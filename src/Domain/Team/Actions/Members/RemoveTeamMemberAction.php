<?php

namespace Domain\Team\Actions\Members;

use App\Workspace\Models\User;
use App\Team\Models\Team;

class RemoveTeamMemberAction
{
    public function execute(Team $team, User $member): void
    {
        $team->removeMember($member);
    }
}

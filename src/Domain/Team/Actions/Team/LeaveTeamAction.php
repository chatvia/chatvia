<?php

namespace Domain\Team\Actions\Team;

use App\Workspace\Models\User;
use App\Team\Models\Team;

class LeaveTeamAction
{
    public function execute(Team $team, User $member): void
    {
        $team->removeMember($member);
    }
}

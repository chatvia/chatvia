<?php

namespace Domain\Membership\Team\Actions\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;

class LeaveTeamAction
{
    public function execute(Team $team, User $member): void
    {
        $team->removeMember($member);
    }
}

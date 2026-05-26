<?php

namespace Domain\Teams\Actions;

use App\Auth\Models\User;
use App\Teams\Models\Team;

class LeaveTeamAction
{
    public function execute(Team $team, User $member): void
    {
        $team->removeMember($member);
    }
}

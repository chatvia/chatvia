<?php

namespace Domain\Teams\Actions\Members;

use App\Auth\Models\User;
use App\Teams\Models\Team;

class RemoveTeamMemberAction
{
    public function execute(Team $team, User $member): void
    {
        $team->removeMember($member);
    }
}

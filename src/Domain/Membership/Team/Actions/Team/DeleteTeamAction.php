<?php

namespace Domain\Membership\Team\Actions\Team;

use Domain\Membership\Team\Models\Team;

class DeleteTeamAction
{
    public function execute(Team $team): void
    {
        $team->delete();
    }
}

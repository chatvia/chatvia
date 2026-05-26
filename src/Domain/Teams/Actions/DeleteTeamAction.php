<?php

namespace Domain\Teams\Actions;

use App\Teams\Models\Team;

class DeleteTeamAction
{
    public function execute(Team $team): void
    {
        $team->delete();
    }
}

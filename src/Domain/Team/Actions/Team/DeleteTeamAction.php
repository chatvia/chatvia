<?php

namespace Domain\Team\Actions\Team;

use App\Team\Models\Team;

class DeleteTeamAction
{
    public function execute(Team $team): void
    {
        $team->delete();
    }
}

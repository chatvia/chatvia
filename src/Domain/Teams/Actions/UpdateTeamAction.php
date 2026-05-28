<?php

namespace Domain\Teams\Actions;

use App\Teams\Models\Team;
use Domain\Teams\Data\UpdateTeamData;

class UpdateTeamAction
{
    public function execute(UpdateTeamData $data): Team
    {
        $data->team->update([
            'name' => $data->name,
            'can_access_dashboard' => $data->canAccessDashboard,
        ]);

        return $data->team->fresh();
    }
}

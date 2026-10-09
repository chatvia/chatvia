<?php

namespace Domain\Membership\Team\Actions\Team;

use Domain\Membership\Team\Data\UpdateTeamData;
use Domain\Membership\Team\Models\Team;

/**
 * Renames a team. Shared by the dashboard and the public API so both return
 * the refreshed model.
 */
class UpdateTeamAction
{
    public function execute(UpdateTeamData $data): Team
    {
        $data->team->update([
            'name' => $data->name,
        ]);

        return $data->team->fresh();
    }
}

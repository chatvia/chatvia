<?php

namespace Domain\Membership\Team\Actions\Team;

use Domain\Membership\Team\Data\UpdateTeamData;
use Domain\Membership\Team\Models\Team;

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

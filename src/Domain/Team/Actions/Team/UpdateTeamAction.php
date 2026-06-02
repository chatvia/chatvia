<?php

namespace Domain\Team\Actions\Team;

use App\Team\Models\Team;
use Domain\Team\Data\UpdateTeamData;

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

<?php

namespace Domain\Membership\Team\Actions\Team;

use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\DB;

class CreateTeamAction
{
    public function __construct(
        private readonly TeamService $teamService,
    ) {}

    public function execute(CreateTeamData $data): Team
    {
        return DB::transaction(function () use ($data): Team {
            $team = Team::create([
                'name' => $data->name,
                'user_id' => $data->owner->id,
            ]);

            $this->teamService->createDefaultRoles($team);

            return $team;
        });
    }
}

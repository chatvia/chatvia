<?php

namespace Domain\Team\Actions\Team;

use App\Team\Models\Team;
use App\Team\Services\TeamService;
use Domain\Team\Data\CreateTeamData;
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

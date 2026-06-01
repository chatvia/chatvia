<?php

namespace Domain\Teams\Actions\Team;

use App\Teams\Models\Team;
use App\Teams\Services\TeamService;
use Domain\Teams\Data\CreateTeamData;
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

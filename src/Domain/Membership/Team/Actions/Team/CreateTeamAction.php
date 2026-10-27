<?php

namespace Domain\Membership\Team\Actions\Team;

use Domain\Membership\Team\Actions\Roles\CreateDefaultRolesAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Creates a team for its owner together with the default roles, in one
 * transaction so a team never exists without its roles. Used for every new
 * team: the dashboard, the API and registration (through
 * CreateDefaultTeamAction).
 */
class CreateTeamAction
{
    public function __construct(
        private readonly CreateDefaultRolesAction $createDefaultRoles,
    ) {}

    public function execute(CreateTeamData $data): Team
    {
        return DB::transaction(function () use ($data): Team {
            $team = Team::query()->create([
                'name' => $data->name,
                'workspace_id' => $data->workspace->id,
            ]);

            $this->createDefaultRoles->execute($team);

            return $team;
        });
    }
}

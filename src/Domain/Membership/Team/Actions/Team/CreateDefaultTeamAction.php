<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Actions\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;

/**
 * Creates the first team of an owner's workspace, named "{name}'s Team",
 * with its default roles. Registration, new workspaces and seeders start
 * every workspace this way.
 */
class CreateDefaultTeamAction
{
    public function __construct(
        private readonly CreateTeamAction $createTeam,
    ) {}

    public function execute(User $owner): Team
    {
        return $this->createTeam->execute(new CreateTeamData(
            workspace: $owner->workspace(),
            name: "{$owner->name}'s Team",
        ));
    }
}

<?php

namespace Domain\Membership\Team\Actions\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Validation\ValidationException;

/**
 * Resolve the owned team an API call targets by its external id. Throws a
 * validation error so the controller surfaces `422 team_id`.
 */
class ResolveOwnedTeamAction
{
    public function execute(User $owner, string $teamExternalId): Team
    {
        /** @var Team|null $team */
        $team = $owner->ownedTeams()->where('external_id', $teamExternalId)->first();

        if (! $team) {
            throw ValidationException::withMessages(['team_id' => 'Team not found.']);
        }

        return $team;
    }
}

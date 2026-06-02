<?php

namespace Domain\Team\Actions\Team;

use App\Workspace\Models\User;
use App\Team\Models\Team;
use Illuminate\Validation\ValidationException;

/**
 * Resolve the team an API call targets: the given owned team (by external id)
 * or the owner's primary team when no id is supplied. Throws a validation
 * error so the controller surfaces `422 team_id`.
 */
class ResolveOwnedTeamAction
{
    public function execute(User $owner, ?string $teamExternalId): Team
    {
        if ($teamExternalId === null || $teamExternalId === '') {
            $team = $owner->primaryTeam();

            if (! $team) {
                throw ValidationException::withMessages([
                    'team_id' => 'Create a team first via POST /api/v1/teams.',
                ]);
            }

            return $team;
        }

        /** @var Team|null $team */
        $team = $owner->ownedTeams()->where('external_id', $teamExternalId)->first();

        if (! $team) {
            throw ValidationException::withMessages(['team_id' => 'Team not found.']);
        }

        return $team;
    }
}

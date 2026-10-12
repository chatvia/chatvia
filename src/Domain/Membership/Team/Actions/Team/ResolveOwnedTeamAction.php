<?php

namespace Domain\Membership\Team\Actions\Team;

use App\Identity\Workspace\Models\Workspace;
use Domain\Membership\Team\Models\Team;
use Support\Exceptions\DomainValidationException;

/**
 * Resolve the workspace team an API call targets by its external id. A team
 * of another workspace is rejected as a `team_id` field error (422), so the
 * response never confirms that someone else's team exists.
 */
class ResolveOwnedTeamAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Workspace $workspace, string $teamExternalId): Team
    {
        /** @var Team|null $team */
        $team = $workspace->teams()->where('external_id', $teamExternalId)->first();

        return $team ?? throw new DomainValidationException('team_id', __('Team not found.'));
    }
}

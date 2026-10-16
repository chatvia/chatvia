<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Actions\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Support\Exceptions\DomainValidationException;

/**
 * Deletes a team, guarding the invariants a workspace cannot lose: the
 * owner's primary team, their only team, and a team that still has agents
 * stay. Shared by the dashboard and the public API so the guards live once.
 */
class DeleteTeamAction
{
    /**
     * @throws DomainValidationException when the team must stay
     */
    public function execute(User $owner, Team $team): void
    {
        if ($owner->isPrimaryTeam($team)) {
            throw new DomainValidationException('team', __('You cannot delete your primary team.'));
        }

        if ($owner->ownedTeams()->count() <= 1) {
            throw new DomainValidationException('team', __('You cannot delete your only team. Create another team first.'));
        }

        if ($team->agents()->exists()) {
            throw new DomainValidationException('team', __('You cannot delete a team that has agents. Please delete or move all agents first.'));
        }

        $team->delete();
    }
}

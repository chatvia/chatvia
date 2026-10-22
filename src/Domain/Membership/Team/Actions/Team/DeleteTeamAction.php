<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Actions\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Support\Exceptions\DomainValidationException;

/**
 * Deletes a team, guarding the invariants a workspace cannot lose: the
 * owner's primary team, their only team, a team that still has agents and a
 * team that still has members stay. Shared by the dashboard and the public
 * API so the guards live once.
 *
 * The agents check counts trashed agents too: they wait for their purge job,
 * and deleting their team would remove them behind the purge's back. The
 * members check runs before the delete because `team_user` restricts it.
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

        if ($team->agents()->withTrashed()->exists()) {
            throw new DomainValidationException('team', __('You cannot delete a team that has agents. Please delete or move all agents first.'));
        }

        if ($team->members()->exists()) {
            throw new DomainValidationException('team', __('You cannot delete a team that has members. Please remove its members first.'));
        }

        $team->delete();
    }
}

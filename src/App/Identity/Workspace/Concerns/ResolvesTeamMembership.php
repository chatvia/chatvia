<?php

namespace App\Identity\Workspace\Concerns;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamRoleResolver;

/**
 * Team-role / permission shim helpers for the
 * {@see User} account. Thin, memoized wrappers
 * around {@see TeamRoleResolver}; new code should inject the resolver directly
 * and skip these shims.
 */
trait ResolvesTeamMembership
{
    /**
     * Get the user's role in a specific team. Thin wrapper around
     * {@see TeamRoleResolver::roleFor}; new code should inject the resolver
     * directly and skip this shim.
     */
    public function teamRole(Team $team): ?Role
    {
        return $this->remember(
            "teamRole:{$team->id}",
            fn (): ?Role => app(TeamRoleResolver::class)->roleFor($this, $team),
        );
    }

    /** @see TeamRoleResolver::hasPermission — via the memoized teamRole(). */
    public function hasTeamPermission(Team $team, string $permission): bool
    {
        $role = $this->teamRole($team);

        return $role !== null && $role->hasPermissionTo($permission);
    }

    /**
     * @see TeamRoleResolver::permissions
     *
     * @return list<string>
     */
    public function teamPermissions(Team $team): array
    {
        return app(TeamRoleResolver::class)->permissions($this, $team);
    }

    /**
     * Get the user's primary team (first owned team created at registration).
     */
    public function primaryTeam(): ?Team
    {
        return $this->remember('primaryTeam', fn (): ?Team => $this->ownedTeams()->oldest()->first());
    }

    /**
     * Check if a team is the user's primary team.
     */
    public function isPrimaryTeam(Team $team): bool
    {
        $primaryTeam = $this->primaryTeam();

        return $primaryTeam && $primaryTeam->id === $team->id;
    }
}

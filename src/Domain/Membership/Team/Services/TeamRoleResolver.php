<?php

namespace Domain\Membership\Team\Services;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;

/**
 * Encapsulates "what can {User} do in {Team}?" so the logic doesn't have to
 * live on the User model. Three call sites:
 *  - {@see User::teamRole}          → role resolution + per-team memoisation
 *  - {@see User::hasTeamPermission} → single-permission check
 *  - {@see User::teamPermissions}   → full permission list (owners get all)
 *
 * The thin shims on User delegate here to keep the existing public surface,
 * while new code can inject TeamRoleResolver directly and skip the User import.
 */
class TeamRoleResolver
{
    public function roleFor(User $user, Team $team): ?Role
    {
        // Owner doesn't have a role pivot row — they have everything.
        if ($user->ownsTeam($team)) {
            return null;
        }

        return Role::query()
            ->join('team_user', 'team_user.role_id', '=', 'roles.id')
            ->where('team_user.team_id', $team->id)
            ->where('team_user.user_id', $user->id)
            ->select('roles.*')
            ->first();
    }

    public function hasPermission(User $user, Team $team, string $permission): bool
    {
        $role = $this->roleFor($user, $team);

        return $role !== null && $role->hasPermissionTo($permission);
    }

    /**
     * Full permission list. Owners get every permission defined in
     * config('team-permissions.permissions'); everyone else gets the
     * permission names attached to their role.
     *
     * @return list<string>
     */
    public function permissions(User $user, Team $team): array
    {
        if ($user->ownsTeam($team)) {
            return array_keys((array) config('team-permissions.permissions'));
        }

        $role = $this->roleFor($user, $team);

        if (! $role) {
            return [];
        }

        return $role->permissions->pluck('name')->all();
    }
}

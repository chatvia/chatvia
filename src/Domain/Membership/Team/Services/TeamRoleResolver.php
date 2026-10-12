<?php

namespace Domain\Membership\Team\Services;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Answers "what can {User} do in {Team}?" so the logic doesn't have to live on
 * the User model. The User's ResolvesTeamMembership shims (`teamRole()`,
 * `hasTeamPermission()`, `teamPermissions()`) delegate here and add per-request
 * memoisation; new code can inject this resolver directly.
 *
 * The team owner has no `team_user` row and holds every permission in the
 * {@see TeamPermissionCatalog}.
 */
class TeamRoleResolver
{
    public function __construct(
        private readonly TeamPermissionCatalog $catalog,
    ) {}

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

        return $role instanceof Role && $role->hasPermissionTo($permission);
    }

    /**
     * Full permission list. Owners get every permission code; everyone else
     * gets the permission names attached to their role.
     *
     * @return list<string>
     */
    public function permissions(User $user, Team $team): array
    {
        if ($user->ownsTeam($team)) {
            return $this->catalog->codes();
        }

        $role = $this->roleFor($user, $team);

        if (! $role instanceof Role) {
            return [];
        }

        return $role->permissions->pluck('name')->all();
    }

    /**
     * The user's permissions in every team they own or belong to, keyed by
     * team id — shared with the dashboard so the UI can hide what a teammate
     * may not do. Two queries in total: owned teams get every code, and one
     * join (team_user → role_has_permissions → permissions) covers memberships.
     *
     * @return array<int, list<string>>
     */
    public function permissionsByTeam(User $user): array
    {
        $allPermissions = $this->catalog->codes();
        $permissions = [];

        foreach ($user->ownedTeams()->pluck('id') as $teamId) {
            $permissions[(int) $teamId] = $allPermissions;
        }

        DB::table('team_user')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'team_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('team_user.user_id', $user->id)
            ->select('team_user.team_id', 'permissions.name')
            ->get()
            ->groupBy('team_id')
            ->each(function (Collection $rows, int $teamId) use (&$permissions): void {
                $permissions[$teamId] ??= $rows->pluck('name')->unique()->values()->all();
            });

        return $permissions;
    }
}

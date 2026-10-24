<?php

declare(strict_types=1);

namespace Domain\Notification\Services;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

/**
 * Who in a workspace should hear about something: the active staff accounts
 * whose team role holds a permission — on one agent, or workspace-wide. Or,
 * for the install itself, its admins. The
 * rules are the dashboard's own ({@see TeamPermissionPolicy}), so a
 * notification only reaches someone who could open the page it links to.
 * Whether each of them wants it is their own switch, checked when it is sent.
 *
 * The policy asks a few queries per person (team, role, role permissions,
 * agent list). Here the same answers come from a fixed handful of queries for
 * the whole workspace: the members, the workspace's teams, every membership
 * with its role permissions, and (for an agent) the roles listing it.
 */
final readonly class NotificationRecipients
{
    /**
     * @return Collection<int, User>
     */
    public function forAgent(Agent $agent, string $permission): Collection
    {
        $members = $this->members($agent->workspace_id);
        $teamIds = $this->workspaceTeamIds($agent->workspace_id);
        $memberships = $this->memberships($members);

        // The roles whose own agent list (`agent_role`) holds this agent.
        $listingRoleIds = DB::table('agent_role')
            ->where('agent_id', $agent->id)
            ->pluck('role_id')
            ->map(fn (int|string $id): int => (int) $id);

        return $members
            ->filter(function (User $user) use ($agent, $permission, $teamIds, $memberships, $listingRoleIds): bool {
                $teamId = $this->teamIdOf($user, $teamIds, $memberships);

                if ($teamId === null) {
                    return false;
                }

                // The owner reaches every agent of the workspace and holds every permission.
                if ($this->ownsTeam($user, $teamId, $teamIds)) {
                    return true;
                }

                $role = $memberships->get($user->id)?->get($teamId);

                if (! $role instanceof BaseCollection || $agent->team_id !== $teamId) {
                    return false;
                }

                $permissions = $this->permissionsOf($role);
                $reachesAgent = $role->first()->code === Role::OWNER_CODE
                    || $permissions->contains(Permission::ACCESS_ALL_AGENTS)
                    || $listingRoleIds->contains((int) $role->first()->role_id);

                return $reachesAgent && $permissions->contains($permission);
            })
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function forWorkspace(int $workspaceId, string $permission): Collection
    {
        $members = $this->members($workspaceId);
        $teamIds = $this->workspaceTeamIds($workspaceId);
        $memberships = $this->memberships($members);

        return $members
            ->filter(function (User $user) use ($permission, $teamIds, $memberships): bool {
                $teamId = $this->teamIdOf($user, $teamIds, $memberships);

                if ($teamId === null) {
                    return false;
                }

                if ($this->ownsTeam($user, $teamId, $teamIds)) {
                    return true;
                }

                $role = $memberships->get($user->id)?->get($teamId);

                return $role instanceof BaseCollection && $this->permissionsOf($role)->contains($permission);
            })
            ->values();
    }

    /**
     * The active admins of the install, who run the admin panel.
     *
     * @return Collection<int, User>
     */
    public function admins(): Collection
    {
        return User::query()
            ->where('is_admin', true)
            ->whereNull('deactivated_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * The workspace's active members, with the membership that says whether
     * each one owns the workspace.
     *
     * @return Collection<int, User>
     */
    private function members(int $workspaceId): Collection
    {
        return User::query()
            ->whereHas('workspaceMembership', fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId))
            ->whereNull('deactivated_at')
            ->with('workspaceMembership')
            ->orderBy('id')
            ->get();
    }

    /**
     * The workspace's teams, oldest first: the owner's primary team is the
     * first one.
     *
     * @return BaseCollection<int, int>
     */
    private function workspaceTeamIds(int $workspaceId): BaseCollection
    {
        return Team::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id);
    }

    /**
     * Every team membership of the members, each with its role and the role's
     * permission names, grouped by user id and then team id. A role without
     * permissions still has one row (with a null name).
     *
     * @param  Collection<int, User>  $members
     * @return BaseCollection<int|string, BaseCollection<int|string, BaseCollection<int, \stdClass>>>
     */
    private function memberships(Collection $members): BaseCollection
    {
        if ($members->isEmpty()) {
            return collect();
        }

        return DB::table('team_user')
            ->join('roles', 'roles.id', '=', 'team_user.role_id')
            ->leftJoin('role_has_permissions', 'role_has_permissions.role_id', '=', 'team_user.role_id')
            ->leftJoin('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('team_user.user_id', $members->modelKeys())
            ->get(['team_user.user_id', 'team_user.team_id', 'team_user.role_id', 'roles.code', 'permissions.name'])
            ->groupBy('user_id')
            ->map(fn (BaseCollection $rows): BaseCollection => $rows->groupBy('team_id'));
    }

    /**
     * The team the dashboard acts in for this person, as
     * {@see User::currentOrPrimaryTeam()} picks it: the current team, else
     * the owner's oldest team, else the oldest team they are a member of.
     *
     * @param  BaseCollection<int, int>  $teamIds
     * @param  BaseCollection<int|string, BaseCollection<int|string, BaseCollection<int, \stdClass>>>  $memberships
     */
    private function teamIdOf(User $user, BaseCollection $teamIds, BaseCollection $memberships): ?int
    {
        if ($user->current_team_id !== null) {
            return (int) $user->current_team_id;
        }

        if ($user->isWorkspaceOwner() && $teamIds->isNotEmpty()) {
            return $teamIds->first();
        }

        $oldest = $memberships->get($user->id)?->keys()->min();

        return $oldest === null ? null : (int) $oldest;
    }

    /**
     * As {@see User::ownsTeam()}: the workspace owner owns every team of the
     * workspace.
     *
     * @param  BaseCollection<int, int>  $teamIds
     */
    private function ownsTeam(User $user, int $teamId, BaseCollection $teamIds): bool
    {
        return $user->isWorkspaceOwner() && $teamIds->contains($teamId);
    }

    /**
     * @param  BaseCollection<int, \stdClass>  $role  the rows of one membership
     * @return BaseCollection<int, string>
     */
    private function permissionsOf(BaseCollection $role): BaseCollection
    {
        return $role->pluck('name')->filter()->values();
    }
}

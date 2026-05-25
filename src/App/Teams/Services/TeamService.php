<?php

namespace App\Teams\Services;

use App\Auth\Models\User;
use App\Teams\Data\Resources\PermissionGroupData;
use App\Teams\Data\Resources\PermissionMetaData;
use App\Teams\Data\Resources\RoleConfigData;
use App\Teams\Data\Resources\TeamAgentData;
use App\Teams\Data\Resources\TeamRoleData;
use App\Teams\Models\Permission;
use App\Teams\Models\Role;
use App\Teams\Models\Team;
use App\Teams\Notifications\TeamInvitationNotification;
use Domain\Agents\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class TeamService
{
    public function createDefaultTeam(User $user): Team
    {
        $team = Team::create([
            'name' => $user->name."'s Team",
            'user_id' => $user->id,
        ]);

        $this->createDefaultRoles($team);

        return $team;
    }

    /**
     * Create default roles for a team based on config.
     */
    public function createDefaultRoles(Team $team): void
    {
        $allPermissionCodes = $this->permissionCodes();

        foreach ($this->defaultRolesConfig() as $code => $config) {
            $role = $this->createRole($team, $code, $config);
            $permissionNames = $this->resolvePermissions($config['permissions'], $allPermissionCodes);
            $this->ensurePermissionsExist($permissionNames);
            $role->syncPermissions(Permission::whereIn('name', $permissionNames)->get());
        }
    }

    public function inviteUser(Team $team, string $email, string $roleCode): void
    {
        $this->validateInvitation($team, $email, $roleCode);

        $role = $team->roles()->where('code', $roleCode)->firstOrFail();

        $invitation = $team->invitations()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);

        Notification::route('mail', $email)
            ->notify(new TeamInvitationNotification($invitation));
    }

    /**
     * Check if team owner can invite members (requires active subscription).
     */
    public function canInviteMembers(Team $team): bool
    {
        return $team->owner->subscribed() || $team->owner->onTrial();
    }

    /**
     * Get max team members based on subscription.
     */
    public function getMaxTeamMembers(Team $team): int
    {
        if (! $this->canInviteMembers($team)) {
            return 1; // Owner only
        }

        return config('teams.max_members', 10);
    }

    public function permissionCodes(): array
    {
        return array_keys($this->permissionsConfigRaw());
    }

    public function permissionsConfig(): array
    {
        return collect($this->permissionsConfigRaw())
            ->map(fn ($data, $code): PermissionMetaData => PermissionMetaData::fromConfig(is_array($data) ? $data : ['label' => $data], $code))
            ->all();
    }

    public function permissionGroups(): array
    {
        return collect(config('team-permissions.groups', []))
            ->map(fn ($data): PermissionGroupData => PermissionGroupData::fromConfig($data))
            ->all();
    }

    /**
     * Get default roles config as DTOs for frontend.
     *
     * @return array<string, RoleConfigData>
     */
    public function defaultRoles(): array
    {
        return collect($this->defaultRolesConfig())
            ->map(fn ($data): RoleConfigData => RoleConfigData::fromConfig($data))
            ->all();
    }

    /**
     * Get team agents as DTOs.
     *
     * @return Collection<int, TeamAgentData>
     */
    public function teamAgents(Team $team): Collection
    {
        return $team->agents()
            ->get(['id', 'external_id', 'name'])
            ->map(fn (Agent $agent): TeamAgentData => TeamAgentData::fromModel($agent));
    }

    /**
     * Get team roles as DTOs.
     *
     * @return Collection<int, TeamRoleData>
     */
    public function teamRoles(Team $team): Collection
    {
        return $team->roles()
            ->with('permissions')
            ->get()
            ->map(fn (Role $role): TeamRoleData => TeamRoleData::fromModel($role));
    }

    /**
     * Check if user can access a specific agent.
     */
    public function canAccessAgent(User $user, Team $team, Agent $agent): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->ownsTeam($team)) {
            return true;
        }

        if ($agent->team_id !== $team->id) {
            return false;
        }

        $role = $user->teamRole($team);

        return $role && $this->roleCanAccessAgent($role, $agent);
    }

    /**
     * Check if a role can access a specific agent.
     */
    public function roleCanAccessAgent(Role $role, Agent $agent): bool
    {
        if ($role->code === 'owner') {
            return true;
        }

        if ($role->hasPermissionTo('agents.access-all')) {
            return true;
        }

        return $role->agents()->where('agents.id', $agent->id)->exists();
    }

    /**
     * Get all agents a user can access in a team.
     */
    public function accessibleAgents(User $user, Team $team): Collection
    {
        if ($user->ownsTeam($team)) {
            return $team->agents;
        }

        $role = $user->teamRole($team);

        if (! $role) {
            return collect();
        }

        return $this->roleAccessibleAgents($role, $team);
    }

    /**
     * Get all agents accessible by a role.
     */
    public function roleAccessibleAgents(Role $role, Team $team): Collection
    {
        if ($role->code === 'owner' || $role->hasPermissionTo('agents.access-all')) {
            return $team->agents;
        }

        return $role->agents()
            ->whereIn('agents.id', $team->agents()->pluck('id'))
            ->get();
    }

    /**
     * Update which agents a role can access.
     */
    public function syncRoleAgents(Role $role, array $agentIds = []): void
    {
        if ($role->hasPermissionTo('agents.access-all')) {
            $role->agents()->detach();
        } else {
            $role->agents()->sync($agentIds);
        }
    }

    /**
     * Get user's permissions for all teams (for Inertia shared data).
     */
    public function userPermissionsMap(User $user): array
    {
        $allPermissions = $this->permissionCodes();
        $permissions = [];

        // Get all teams (owned + member)
        $allTeams = $user->ownedTeams->concat($user->memberTeams);

        foreach ($allTeams as $team) {
            $permissions[$team->id] = $user->ownsTeam($team)
                ? $allPermissions
                : $user->teamPermissions($team);
        }

        return $permissions;
    }

    private function permissionsConfigRaw(): array
    {
        return config('team-permissions.permissions', []);
    }

    private function defaultRolesConfig(): array
    {
        return config('team-permissions.roles', []);
    }

    private function validateInvitation(Team $team, string $email, string $roleCode): void
    {
        if ($team->hasUserWithEmail($email)) {
            throw new RuntimeException('This user already belongs to the team.');
        }

        if (! $team->roles()->where('code', $roleCode)->exists()) {
            throw new RuntimeException("The role '{$roleCode}' does not exist for this team.");
        }
    }

    private function createRole(Team $team, string $code, array $config): Role
    {
        return Role::firstOrCreate(
            [
                'name' => $config['name'],
                'team_id' => $team->id,
                'guard_name' => 'web',
            ],
            [
                'code' => $code,
                'description' => $config['description'] ?? null,
            ]
        );
    }

    private function resolvePermissions(array $permissions, array $allPermissions): array
    {
        return in_array('*', $permissions) ? $allPermissions : $permissions;
    }

    private function ensurePermissionsExist(array $permissionNames): void
    {
        foreach ($permissionNames as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }
    }
}

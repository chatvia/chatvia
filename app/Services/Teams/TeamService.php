<?php

namespace App\Services\Teams;

use App\Data\Resources\Team\PermissionGroupData;
use App\Data\Resources\Team\PermissionMetaData;
use App\Data\Resources\Team\RoleConfigData;
use App\Data\Resources\Team\TeamAgentData;
use App\Data\Resources\Team\TeamRoleData;
use App\Models\Agent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class TeamService
{
    // =========================================================================
    // Team Creation & Setup
    // =========================================================================

    /**
     * Create a default team for a new user with owner role.
     */
    public function createDefaultTeam(User $user): Team
    {
        $team = Team::create([
            'name' => $user->name."'s Team",
            'user_id' => $user->id,
        ]);

        $this->createDefaultRoles($team);
        $this->attachOwner($team, $user);

        return $team;
    }

    /**
     * Create default roles for a team based on config.
     */
    public function createDefaultRoles(Team $team): void
    {
        $allPermissionCodes = $this->allPermissionCodes();

        foreach ($this->defaultRolesConfig() as $code => $config) {
            $role = $this->createRole($team, $code, $config);
            $permissionNames = $this->resolvePermissions($config['permissions'], $allPermissionCodes);
            $this->ensurePermissionsExist($permissionNames);
            $role->syncPermissions(Permission::whereIn('name', $permissionNames)->get());
        }
    }

    // =========================================================================
    // Team Invitations
    // =========================================================================

    /**
     * Invite a user to a team by email and role code.
     */
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

    // =========================================================================
    // Permission & Role DTOs
    // =========================================================================

    /**
     * Get all permission codes defined in config.
     */
    public function allPermissionCodes(): array
    {
        return array_keys($this->permissionsConfigRaw());
    }

    /**
     * Get permission metadata as DTOs for frontend.
     *
     * @return array<string, PermissionMetaData>
     */
    public function permissionsConfig(): array
    {
        return collect($this->permissionsConfigRaw())
            ->map(fn ($data, $code) => PermissionMetaData::fromConfig(is_array($data) ? $data : ['label' => $data], $code))
            ->all();
    }

    /**
     * Get permission groups as DTOs for frontend.
     *
     * @return array<string, PermissionGroupData>
     */
    public function permissionGroups(): array
    {
        return collect(config('team-permissions.groups', []))
            ->map(fn ($data) => PermissionGroupData::fromConfig($data))
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
            ->map(fn ($data) => RoleConfigData::fromConfig($data))
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
            ->get(['id', 'name'])
            ->map(fn (Agent $agent) => TeamAgentData::fromModel($agent));
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
            ->map(fn (Role $role) => TeamRoleData::fromModel($role));
    }

    // =========================================================================
    // Agent Access Control
    // =========================================================================

    /**
     * Check if user can access a specific agent.
     */
    public function canAccessAgent(User $user, Team $team, Agent $agent): bool
    {
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

    // =========================================================================
    // User Permissions
    // =========================================================================

    /**
     * Get user's permissions for all teams (for Inertia shared data).
     */
    public function userPermissionsMap(User $user): array
    {
        $allPermissions = $this->allPermissionCodes();
        $permissions = [];

        foreach ($user->teams as $team) {
            $permissions[$team->id] = $user->ownsTeam($team)
                ? $allPermissions
                : $user->teamPermissions($team);
        }

        return $permissions;
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

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

    private function attachOwner(Team $team, User $user): void
    {
        $ownerRole = $team->roles()->where('code', 'owner')->first();

        if ($ownerRole) {
            $team->users()->attach($user->id, [
                'role_id' => $ownerRole->id,
                'is_owner' => true,
            ]);
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

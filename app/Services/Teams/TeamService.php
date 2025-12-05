<?php

namespace App\Services\Teams;

use App\Models\Agent;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Jurager\Teams\Events\TeamMemberInvited;
use Jurager\Teams\Events\TeamMemberInviting;
use RuntimeException;

class TeamService
{
    /**
     * Invite a user to a team.
     * This bypasses the package's default email and uses our custom notification.
     */
    public function inviteUser(Team $team, string $email, int|string $keyword): void
    {
        if ($team->hasUserWithEmail($email)) {
            throw new RuntimeException('This user already belongs to the team.');
        }

        if (! $role = $team->getRole($keyword)) {
            throw new RuntimeException("The role '{$keyword}' does not exist or does not belong to the team.");
        }

        // Dispatch event before inviting the user
        TeamMemberInviting::dispatch($team, $email, $role);

        $invitation = $team->invitations()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);

        // Dispatch event after inviting the user
        TeamMemberInvited::dispatch($team, $email, $role);

        // Send our custom invitation notification
        Notification::route('mail', $email)
            ->notify(new TeamInvitationNotification($invitation));
    }

    /**
     * Check if a team owner can invite members.
     */
    public function canInviteMembers(Team $team): bool
    {
        $owner = $team->owner;

        return $owner->subscribed() || $owner->onTrial();
    }

    /**
     * Get the maximum number of team members allowed based on subscription.
     */
    public function getMaxTeamMembers(Team $team): int
    {
        $owner = $team->owner;

        // Default to 1 (owner only) if no subscription
        if (! $owner->subscribed() && ! $owner->onTrial()) {
            return 1;
        }

        // You can customize this based on subscription plans
        return config('teams.max_members', 10);
    }

    /**
     * Get default roles configuration.
     */
    public function getDefaultRoles(): array
    {
        return config('team-permissions.roles', []);
    }

    /**
     * Get all available permissions with full metadata.
     */
    public function getAvailablePermissions(): array
    {
        return config('team-permissions.permissions', []);
    }

    /**
     * Get permission labels for UI display (code => label).
     */
    public function getPermissionLabels(): array
    {
        $permissions = $this->getAvailablePermissions();

        return collect($permissions)->mapWithKeys(function ($data, $code) {
            $label = is_array($data) ? ($data['label'] ?? $code) : $data;

            return [$code => $label];
        })->all();
    }

    /**
     * Get all permission codes.
     */
    public function getPermissionCodes(): array
    {
        return array_keys($this->getAvailablePermissions());
    }

    /**
     * Get permission groups for UI display.
     */
    public function getPermissionGroups(): array
    {
        return config('team-permissions.groups', []);
    }

    /**
     * Get all agents for a team.
     */
    public function getAllAgentsForTeam(Team $team): Collection
    {
        return $team->agents()->get(['id', 'name']);
    }

    /**
     * Create a default team for a user.
     */
    public function createDefaultTeam(User $user): Team
    {
        $team = new Team;
        $team->name = $user->name."'s Team";
        $team->user_id = $user->id;
        $team->save();

        // Create default roles for the team
        $this->createDefaultRoles($team);

        return $team;
    }

    /**
     * Create default roles for a team.
     */
    public function createDefaultRoles(Team $team): void
    {
        $roles = $this->getDefaultRoles();
        $allPermissions = $this->getPermissionCodes();

        foreach ($roles as $code => $roleConfig) {
            // Handle wildcard permissions for owner
            $permissions = $roleConfig['permissions'];
            if (in_array('*', $permissions)) {
                $permissions = $allPermissions;
            }

            $team->addRole(
                $code,
                $permissions,
                $roleConfig['name'],
                $roleConfig['description'] ?? null
            );
        }
    }

    /**
     * Get user permissions for all teams (for Inertia shared data).
     */
    public function getUserPermissionsForInertia(User $user): array
    {
        $permissions = [];
        $allPermissions = $this->getPermissionCodes();

        foreach ($user->allTeams() as $team) {
            // Owner has all permissions
            if ($user->ownsTeam($team)) {
                $permissions[$team->id] = $allPermissions;

                continue;
            }

            // Get user's role permissions in the team
            $teamPermissions = $user->teamPermissions($team);
            $permissions[$team->id] = is_array($teamPermissions) ? $teamPermissions : $teamPermissions->toArray();
        }

        return $permissions;
    }

    /**
     * Check if a user has access to a specific agent.
     */
    public function userHasAgentAccess(User $user, Team $team, Agent $agent): bool
    {
        // Owner has access to all agents
        if ($user->ownsTeam($team)) {
            return true;
        }

        // Check if agent belongs to the team (via owner)
        if ($agent->user_id !== $team->user_id) {
            return false;
        }

        $role = $user->teamRole($team);
        if (! $role) {
            return false;
        }

        return $this->roleHasAgentAccess($role, $agent);
    }

    /**
     * Check if a role has access to a specific agent.
     */
    public function roleHasAgentAccess(Role $role, Agent $agent): bool
    {
        // Owner role has access to all agents
        if ($role->code === 'owner') {
            return true;
        }

        // Check if role has access to all agents
        if ($role->all_agents) {
            return true;
        }

        // Check if agent is in the role's agent list via relationship
        return $role->agents()->where('agents.id', $agent->id)->exists();
    }

    /**
     * Get all agents a user can access in a team.
     */
    public function getUserAccessibleAgents(User $user, Team $team): Collection
    {
        // Owner has access to all agents
        if ($user->ownsTeam($team)) {
            return $team->agents;
        }

        $role = $user->teamRole($team);
        if (! $role) {
            return collect();
        }

        return $this->getRoleAccessibleAgents($role, $team);
    }

    /**
     * Get all agents a role can access in a team.
     */
    public function getRoleAccessibleAgents(Role $role, Team $team): Collection
    {
        // Owner role or all_agents flag = all team agents
        if ($role->code === 'owner' || $role->all_agents) {
            return $team->agents;
        }

        // Get agents via relationship, filtered to team's agents
        $teamAgentIds = $team->agents()->pluck('id');

        return $role->agents()->whereIn('agents.id', $teamAgentIds)->get();
    }

    /**
     * Update agent access for a role.
     */
    public function updateRoleAgentAccess(Role $role, bool $allAgents, array $agentIds = []): void
    {
        $role->all_agents = $allAgents;
        $role->save();

        // Sync agents via relationship
        if ($allAgents) {
            // If all agents, clear the specific agent assignments
            $role->agents()->detach();
        } else {
            // Sync specific agents
            $role->agents()->sync($agentIds);
        }
    }

    /**
     * Get role with agent access data formatted for frontend.
     */
    public function formatRoleForFrontend(Role $role, Team $team): array
    {
        return [
            'id' => $role->id,
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $role->permissions->pluck('code')->toArray(),
            'all_agents' => (bool) $role->all_agents,
            'agent_ids' => $role->agents()->pluck('agents.id')->toArray(),
        ];
    }
}

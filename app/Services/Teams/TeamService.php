<?php

namespace App\Services\Teams;

use App\Models\Team;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
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
     * Get all available permissions.
     */
    public function getAvailablePermissions(): array
    {
        return config('team-permissions.permissions', []);
    }

    /**
     * Get permission groups for UI display.
     */
    public function getPermissionGroups(): array
    {
        return config('team-permissions.groups', []);
    }

    /**
     * Create default roles for a team.
     */
    public function createDefaultRoles(Team $team): void
    {
        $roles = $this->getDefaultRoles();
        $allPermissions = array_keys($this->getAvailablePermissions());

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
        $allPermissions = array_keys($this->getAvailablePermissions());

        foreach ($user->allTeams() as $team) {
            // Owner has all permissions
            if ($user->ownsTeam($team)) {
                $permissions[$team->id] = $allPermissions;

                continue;
            }

            // Get user's role permissions in the team
            $permissions[$team->id] = $user->teamPermissions($team)->toArray();
        }

        return $permissions;
    }
}

<?php

namespace App\Http\Resources\Team;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\Resources\Members\ManagedUserData;
use Domain\Membership\Team\Data\Resources\Members\TeamInvitationData;
use Domain\Membership\Team\Data\Resources\Members\TeamMemberData;
use Domain\Membership\Team\Data\Resources\Roles\TeamRoleData;
use Domain\Membership\Team\Data\Resources\Team\TeamAgentData;
use Domain\Membership\Team\Data\Resources\Team\TeamData;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamPermissionCatalog;

/**
 * Props of the team page, drawn from many sources: members with their roles,
 * the team's roles and agents, pending invitations, the managed users who can
 * be added without an invitation, and the permission catalog.
 */
final readonly class TeamShowPageProps
{
    public function __construct(
        private TeamPermissionCatalog $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Team $team, User $user): array
    {
        // Owner (no pivot role row — holds everything) + pivot members. Resolve
        // every member's role in ONE query keyed by the pivot's role_id, instead
        // of TeamMemberData::fromUser running a roles⋈team_user join per member.
        $owner = $team->owner;
        $teamMembers = $team->members;

        $rolesById = Role::query()
            ->whereIn('id', $teamMembers->pluck('pivot.role_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        // role_id per member id, read through the pivot via the string accessor
        // (the dynamic ->pivot property isn't visible on a bare User to static
        // analysis). Keeps the single-roles-query optimisation above.
        $roleIdByMemberId = $teamMembers->pluck('pivot.role_id', 'id');

        $members = collect([TeamMemberData::fromUser($owner, $team, null)])
            ->concat($teamMembers->map(
                fn (User $member): TeamMemberData => TeamMemberData::fromUser(
                    $member,
                    $team,
                    $rolesById->get($roleIdByMemberId->get($member->id)),
                ),
            ));

        $pendingInvitations = $team
            ->invitations()
            ->with('role')
            ->get()
            ->map(fn (Invitation $invitation): TeamInvitationData => TeamInvitationData::fromModel($invitation));

        $currentMemberIds = collect([$owner])->concat($teamMembers)->pluck('id')->toArray();
        $managedUsers = $user->workspace()->teammates()
            ->whereNotIn('users.id', $currentMemberIds)
            ->get()
            ->map(fn (User $managedUser): ManagedUserData => ManagedUserData::fromModel($managedUser));

        return [
            'team' => TeamData::fromModel($team),
            'members' => $members,
            'roles' => TeamRoleData::collectForTeam($team),
            'agents' => TeamAgentData::collectForTeam($team),
            'pendingInvitations' => $pendingInvitations,
            'managedUsers' => $managedUsers,
            'isOwner' => $user->ownsTeam($team),
            'userRole' => $user->teamRole($team)?->code ?? Role::OWNER_CODE,
            'availableRoles' => $this->catalog->defaultRoles(),
            'permissionsConfig' => $this->catalog->permissions(),
            'permissionGroups' => $this->catalog->groups(),
        ];
    }
}

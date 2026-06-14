<?php

namespace App\Http\Controllers\Team\Crud;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\Resources\Members\ManagedUserData;
use Domain\Membership\Team\Data\Resources\Members\TeamInvitationData;
use Domain\Membership\Team\Data\Resources\Members\TeamMemberData;
use Domain\Membership\Team\Data\Resources\Team\TeamData;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamShowController
{
    public function __invoke(Request $request, Team $team, TeamService $teamService)
    {
        $user = $request->user();

        if ($user->cannot('view', $team)) {
            return back();
        }

        // Owner (no pivot role row — holds everything) + pivot members. Resolve
        // every member's role in ONE query keyed by the pivot's role_id, instead
        // of TeamMemberData::fromUser running a roles⋈team_user join per member.
        $owner = $team->owner;
        $teamMembers = $team->members;

        $rolesById = Role::query()
            ->whereIn('id', $teamMembers->pluck('pivot.role_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $allMembers = collect([$owner])->concat($teamMembers);

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

        $roles = $teamService->teamRoles($team);

        $agents = $teamService->teamAgents($team);

        $pendingInvitations = $team
            ->invitations()
            ->with('role')
            ->get()
            ->map(fn ($invitation): TeamInvitationData => TeamInvitationData::fromModel($invitation));

        $currentMemberIds = $allMembers->pluck('id')->toArray();
        $managedUsers = $user->managedUsers()
            ->whereNotIn('id', $currentMemberIds)
            ->get()
            ->map(fn ($managedUser): ManagedUserData => ManagedUserData::fromModel($managedUser));

        return Inertia::render('teams/Show', [
            'team' => TeamData::fromModel($team),
            'members' => $members,
            'roles' => $roles,
            'agents' => $agents,
            'pendingInvitations' => $pendingInvitations,
            'managedUsers' => $managedUsers,
            'isOwner' => $user->ownsTeam($team),
            'userRole' => $user->teamRole($team)->code ?? 'owner',
            'availableRoles' => $teamService->defaultRoles(),
            'permissionsConfig' => $teamService->permissionsConfig(),
            'permissionGroups' => $teamService->permissionGroups(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Team\Crud;

use Domain\Membership\Team\Data\Resources\Members\ManagedUserData;
use Domain\Membership\Team\Data\Resources\Members\TeamInvitationData;
use Domain\Membership\Team\Data\Resources\Members\TeamMemberData;
use Domain\Membership\Team\Data\Resources\Team\TeamData;
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

        // Get all team members (owner + members)
        $allMembers = collect([$team->owner])->concat($team->members);

        $members = $allMembers->map(
            fn ($member): TeamMemberData => TeamMemberData::fromUser($member, $team)
        );

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

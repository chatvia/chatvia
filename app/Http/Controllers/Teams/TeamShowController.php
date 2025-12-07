<?php

namespace App\Http\Controllers\Teams;

use App\Data\Resources\Team\ManagedUserData;
use App\Data\Resources\Team\TeamData;
use App\Data\Resources\Team\TeamInvitationData;
use App\Data\Resources\Team\TeamMemberData;
use App\Models\Team;
use App\Services\Teams\TeamService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamShowController
{
    public function __invoke(Request $request, Team $team, TeamService $teamService)
    {
        $user = $request->user();

        if (! $user->belongsToTeam($team) && ! $user->ownsTeam($team)) {
            abort(403, 'You do not have access to this team.');
        }

        $members = $team->users->map(
            fn ($member) => TeamMemberData::fromUser($member, $team)
        );

        $roles = $teamService->teamRoles($team);

        $agents = $teamService->teamAgents($team);

        $pendingInvitations = $team
            ->invitations()
            ->with('role')
            ->get()
            ->map(fn ($invitation) => TeamInvitationData::fromModel($invitation));

        $currentMemberIds = $team->users->pluck('id')->toArray();
        $managedUsers = $user->managedUsers()
            ->whereNotIn('id', $currentMemberIds)
            ->get()
            ->map(fn ($managedUser) => ManagedUserData::fromModel($managedUser));

        return Inertia::render('teams/Show', [
            'team' => TeamData::fromModel($team),
            'members' => $members,
            'roles' => $roles,
            'agents' => $agents,
            'pendingInvitations' => $pendingInvitations,
            'managedUsers' => $managedUsers,
            'isOwner' => $user->ownsTeam($team),
            'userRole' => $user->teamRole($team)?->code ?? 'owner',
            'availableRoles' => $teamService->defaultRoles(),
            'permissionsConfig' => $teamService->permissionsConfig(),
            'permissionGroups' => $teamService->permissionGroups(),
        ]);
    }
}

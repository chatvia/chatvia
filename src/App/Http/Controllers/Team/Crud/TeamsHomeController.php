<?php

namespace App\Http\Controllers\Team\Crud;

use Domain\Membership\Team\Data\Resources\Team\TeamListItemData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamsHomeController
{
    public function __invoke(Request $request, TeamService $teamService)
    {
        $user = $request->user();

        $owned = $user->ownedTeams()
            ->with('owner')
            ->withCount(['members', 'agents'])
            ->get();

        $member = $user->memberTeams()
            ->with('owner')
            ->withCount(['members', 'agents'])
            ->get();

        $teams = $owned->concat($member)
            ->map(fn ($team): TeamListItemData => TeamListItemData::fromTeamForUser($team, $user))
            ->values()
            ->all();

        return Inertia::render('teams/Index', [
            'teams' => $teams,
            'availableRoles' => $teamService->defaultRoles(),
        ]);
    }
}

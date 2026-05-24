<?php

namespace App\Http\Controllers\Teams;

use App\Teams\Data\Resources\TeamListItemData;
use App\Teams\Services\TeamService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamsHomeController
{
    public function __invoke(Request $request, TeamService $teamService)
    {
        $user = $request->user();

        // Get all teams (owned + member)
        $allTeams = $user->ownedTeams->concat($user->memberTeams);

        $teams = $allTeams
            ->map(fn ($team) => TeamListItemData::fromTeamForUser($team, $user))
            ->values()
            ->all();

        return Inertia::render('teams/Index', [
            'teams' => $teams,
            'availableRoles' => $teamService->defaultRoles(),
        ]);
    }
}

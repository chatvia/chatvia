<?php

namespace App\Http\Controllers\Teams;

use App\Data\Resources\Team\TeamListItemData;
use App\Services\Teams\TeamService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamsHomeController
{
    public function __invoke(Request $request, TeamService $teamService)
    {
        $user = $request->user();

        $teams = $user->teams
            ->map(fn ($team) => TeamListItemData::fromTeamForUser($team, $user))
            ->values()
            ->all();

        return Inertia::render('teams/Index', [
            'teams' => $teams,
            'availableRoles' => $teamService->defaultRoles(),
        ]);
    }
}

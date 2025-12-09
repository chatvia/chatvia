<?php

namespace App\Http\Controllers\Teams;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamListController
{
    /**
     * Get all teams for the authenticated user.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'teams' => [],
            ]);
        }

        // Get all teams (owned + member)
        $ownedTeams = $user->ownedTeams->map(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'is_owner' => true,
            'is_primary' => $team->id === $user->primaryTeam()?->id,
        ]);

        $memberTeams = $user->memberTeams->map(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'is_owner' => false,
            'is_primary' => false,
        ]);

        $teams = $ownedTeams->concat($memberTeams)->values();

        return response()->json([
            'teams' => $teams,
        ]);
    }
}

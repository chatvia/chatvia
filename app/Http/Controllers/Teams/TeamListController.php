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

        // Get all teams (owner + members) from team_user pivot
        $teams = $user->teams()->get()->map(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'is_owner' => (bool) $team->pivot->is_owner,
            'is_primary' => $team->id === $user->primaryTeam()?->id,
        ])->values();

        return response()->json([
            'teams' => $teams,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Team\Switching;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON list of the user's teams (owned first) for the team switcher.
 */
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

        // Get all teams (owned + member). `external_id` is required: the
        // teams.switch route binds {team} by external_id (Team::getRouteKeyName),
        // and the switcher hands it to Ziggy — omitting it breaks team switching.
        $ownedTeams = $user->ownedTeams->map(fn ($team): array => [
            'id' => $team->id,
            'external_id' => $team->external_id,
            'name' => $team->name,
            'is_owner' => true,
            'is_primary' => $team->id === $user->primaryTeam()?->id,
        ]);

        $memberTeams = $user->memberTeams->map(fn ($team): array => [
            'id' => $team->id,
            'external_id' => $team->external_id,
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

<?php

namespace App\Http\Controllers\Team\Crud;

use Domain\Membership\Team\Data\Resources\Team\TeamListItemData;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamPermissionCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Teams page: every team the user owns or belongs to.
 */
class TeamsHomeController
{
    public function __invoke(Request $request, TeamPermissionCatalog $catalog): Response
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
            ->map(fn (Team $team): TeamListItemData => TeamListItemData::fromTeamForUser($team, $user))
            ->values()
            ->all();

        return Inertia::render('teams/Index', [
            'teams' => $teams,
            'availableRoles' => $catalog->defaultRoles(),
        ]);
    }
}

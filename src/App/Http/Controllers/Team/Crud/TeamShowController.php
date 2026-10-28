<?php

namespace App\Http\Controllers\Team\Crud;

use App\Http\Resources\Team\TeamShowPageProps;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The team page: members with their roles, the team's roles and agents,
 * pending invitations, and the managed users who can be added without an
 * invitation. Non-members are sent back.
 */
class TeamShowController
{
    public function __invoke(Request $request, Team $team, TeamShowPageProps $props): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->cannot('view', $team)) {
            return back();
        }

        return Inertia::render('dashboard/teams/pages/Show', $props->for($team, $user));
    }
}

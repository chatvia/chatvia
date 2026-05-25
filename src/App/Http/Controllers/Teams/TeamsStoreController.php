<?php

namespace App\Http\Controllers\Teams;

use App\Auth\Models\User;
use App\Teams\Models\Team;
use App\Teams\Services\TeamService;
use Illuminate\Http\Request;

class TeamsStoreController
{
    public function __invoke(Request $request, TeamService $teamService)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! $user->canCreateTeam()) {
            return back()->withErrors([
                'name' => "You've reached your team limit ({$user->quota->teams_limit}). Upgrade your plan to create more teams.",
            ]);
        }

        $team = Team::create([
            'name' => $validated['name'],
            'user_id' => $user->id,
        ]);

        $teamService->createDefaultRoles($team);

        return redirect()
            ->route('teams.show', $team)
            ->with('success', 'Team created successfully.');
    }
}

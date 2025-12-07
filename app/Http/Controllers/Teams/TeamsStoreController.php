<?php

namespace App\Http\Controllers\Teams;

use App\Models\Team;
use App\Services\Teams\TeamService;
use Illuminate\Http\Request;

class TeamsStoreController
{
    public function __invoke(Request $request, TeamService $teamService)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $team = Team::create([
            'name' => $validated['name'],
            'user_id' => $user->id,
        ]);

        $teamService->createDefaultRoles($team);

        $ownerRole = $team->roles()->where('code', 'owner')->first();

        if ($ownerRole) {
            $team->users()->attach($user->id, [
                'role_id' => $ownerRole->id,
                'is_owner' => true,
            ]);
        }

        return redirect()
            ->route('teams.show', $team)
            ->with('success', 'Team created successfully.');
    }
}

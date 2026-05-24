<?php

namespace App\Http\Controllers\Teams;

use App\Teams\Models\Team;
use Illuminate\Http\Request;

class TeamUpdateController
{
    public function __invoke(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            return back()->withErrors(['team' => 'Only the team owner can update the team.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team->update($validated);

        return back()->with('success', 'Team updated successfully.');
    }
}

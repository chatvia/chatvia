<?php

namespace App\Http\Controllers\Team\Crud;

use App\Http\Requests\Team\UpdateTeamRequest;
use App\Team\Models\Team;
use Domain\Team\Actions\Team\UpdateTeamAction;
use Illuminate\Http\RedirectResponse;

class TeamUpdateController
{
    public function __invoke(UpdateTeamRequest $request, Team $team, UpdateTeamAction $updateTeam): RedirectResponse
    {
        $updateTeam->execute($request->toData($team));

        return back()->with('success', 'Team updated successfully.');
    }
}

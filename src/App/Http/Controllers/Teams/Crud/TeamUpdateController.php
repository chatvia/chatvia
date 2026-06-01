<?php

namespace App\Http\Controllers\Teams\Crud;

use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Teams\Models\Team;
use Domain\Teams\Actions\Team\UpdateTeamAction;
use Illuminate\Http\RedirectResponse;

class TeamUpdateController
{
    public function __invoke(UpdateTeamRequest $request, Team $team, UpdateTeamAction $updateTeam): RedirectResponse
    {
        $updateTeam->execute($request->toData($team));

        return back()->with('success', 'Team updated successfully.');
    }
}

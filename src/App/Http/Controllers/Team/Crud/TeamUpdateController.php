<?php

namespace App\Http\Controllers\Team\Crud;

use App\Http\Requests\Team\UpdateTeamRequest;
use Domain\Membership\Team\Actions\Team\UpdateTeamAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;

class TeamUpdateController
{
    public function __invoke(UpdateTeamRequest $request, Team $team, UpdateTeamAction $updateTeam): RedirectResponse
    {
        $updateTeam->execute($request->toData($team));

        return back()->with('success', 'Team updated successfully.');
    }
}

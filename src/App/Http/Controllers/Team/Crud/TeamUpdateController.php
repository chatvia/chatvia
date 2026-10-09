<?php

namespace App\Http\Controllers\Team\Crud;

use App\Http\Requests\Team\UpdateTeamRequest;
use Domain\Membership\Team\Actions\Team\UpdateTeamAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\RedirectResponse;

/**
 * Renames a team. Only the team owner may do this ({@see UpdateTeamRequest}
 * asks the team policy); the change itself is made by {@see UpdateTeamAction}.
 */
class TeamUpdateController
{
    public function __invoke(UpdateTeamRequest $request, Team $team, UpdateTeamAction $updateTeam): RedirectResponse
    {
        $updateTeam->execute($request->toData($team));

        return back()->with('success', __('Team updated successfully.'));
    }
}

<?php

namespace App\Http\Controllers\Team\Crud;

use App\Http\Requests\Team\CreateTeamRequest;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Illuminate\Http\RedirectResponse;

/**
 * Creates a team from the Teams page.
 */
class TeamsStoreController
{
    public function __invoke(CreateTeamRequest $request, CreateTeamAction $createTeam): RedirectResponse
    {
        $team = $createTeam->execute($request->toData());

        return redirect()
            ->route('teams.show', $team)
            ->with('success', __('Team created successfully.'));
    }
}

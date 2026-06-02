<?php

namespace App\Http\Controllers\Team\Crud;

use App\Workspace\Models\User;
use App\Http\Requests\Team\CreateTeamRequest;
use Domain\Team\Actions\Team\CreateTeamAction;
use Illuminate\Http\RedirectResponse;

class TeamsStoreController
{
    public function __invoke(CreateTeamRequest $request, CreateTeamAction $createTeam): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canCreateTeam()) {
            return back()->withErrors([
                'name' => "You've reached your team limit ({$user->quota->teams_limit}). Upgrade your plan to create more teams.",
            ]);
        }

        $team = $createTeam->execute($request->toData());

        return redirect()
            ->route('teams.show', $team)
            ->with('success', 'Team created successfully.');
    }
}

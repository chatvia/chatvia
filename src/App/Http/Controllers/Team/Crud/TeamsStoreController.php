<?php

namespace App\Http\Controllers\Team\Crud;

use App\Http\Requests\Team\CreateTeamRequest;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Illuminate\Http\RedirectResponse;

/**
 * Creates a team from the Teams page, within the plan's team limit.
 */
class TeamsStoreController
{
    public function __invoke(CreateTeamRequest $request, CreateTeamAction $createTeam): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canCreateTeam()) {
            return back()->withErrors([
                'name' => __("You've reached your team limit (:limit). Upgrade your plan to create more teams.", ['limit' => $user->quotaLimit('teams_limit')]),
            ]);
        }

        $team = $createTeam->execute($request->toData());

        return redirect()
            ->route('teams.show', $team)
            ->with('success', __('Team created successfully.'));
    }
}

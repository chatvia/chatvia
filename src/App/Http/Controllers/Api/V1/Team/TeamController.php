<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Workspace\Models\User;
use App\Http\Resources\Api\V1\Team\TeamData;
use App\Team\Models\Team;
use Dedoc\Scramble\Attributes\Group;
use Domain\Team\Actions\Team\CreateTeamAction;
use Domain\Team\Actions\Team\UpdateTeamAction;
use Domain\Team\Data\CreateTeamData;
use Domain\Team\Data\TeamPayloadData;
use Domain\Team\Data\UpdateTeamData;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Teams organise your agents. Each agent belongs to a team — create a team
 * here, then create agents in it via `POST /api/v1/agents` with the team's
 * `id`.
 */
#[Group(name: 'Teams', weight: 40)]
class TeamController
{
    /**
     * List teams
     *
     * Returns every team you own, each with its agents and roles nested.
     *
     * @return array{data: list<TeamData>}
     */
    public function index(Request $request): array
    {
        $teams = $this->accountOwner($request)
            ->ownedTeams()
            ->with(['agents', 'roles.permissions', 'roles.agents'])
            ->latest()
            ->get();

        return ['data' => $teams->map(fn (Team $team): TeamData => TeamData::fromModel($team))->all()];
    }

    /**
     * Create team
     *
     * @return array{data: TeamData}
     */
    public function store(Request $request, TeamPayloadData $payload, CreateTeamAction $action): array
    {
        $owner = $this->accountOwner($request);

        if (! $owner->canCreateTeam()) {
            throw ValidationException::withMessages([
                'name' => 'You have reached your team limit.',
            ]);
        }

        return ['data' => TeamData::fromModel($action->execute(
            new CreateTeamData(owner: $owner, name: $payload->name),
        ))];
    }

    /**
     * Update team
     *
     * @return array{data: TeamData}
     */
    public function update(Request $request, Team $team, TeamPayloadData $payload, UpdateTeamAction $action): array
    {
        abort_unless($this->accountOwner($request)->ownsTeam($team), 403);

        return ['data' => TeamData::fromModel($action->execute(
            new UpdateTeamData(team: $team, name: $payload->name),
        ))];
    }

    private function accountOwner(Request $request): User
    {
        /** @var User $account */
        $account = $request->user();

        return $account->belongsToWorkspace() ? $account->workspace : $account;
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Teams;

use App\Auth\Models\User;
use App\Http\Resources\Api\V1\TeamData;
use App\Teams\Models\Team;
use Dedoc\Scramble\Attributes\Group;
use Domain\Teams\Actions\CreateTeamAction;
use Domain\Teams\Actions\UpdateTeamAction;
use Domain\Teams\Data\CreateTeamData;
use Domain\Teams\Data\TeamPayloadData;
use Domain\Teams\Data\UpdateTeamData;
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
            new CreateTeamData(owner: $owner, name: $payload->name, canAccessDashboard: $payload->can_access_dashboard),
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
            new UpdateTeamData(team: $team, name: $payload->name, canAccessDashboard: $payload->can_access_dashboard),
        ))];
    }

    private function accountOwner(Request $request): User
    {
        /** @var User $account */
        $account = $request->user();

        return $account->isManagedUser() ? $account->manager : $account;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Requests\Api\V1\Team\StoreApiTeamRequest;
use App\Http\Requests\Api\V1\Team\UpdateApiTeamRequest;
use App\Http\Resources\Api\V1\Team\TeamData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Actions\Team\DeleteTeamAction;
use Domain\Membership\Team\Actions\Team\UpdateTeamAction;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Support\Pagination\CursorPage;

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
     * Returns the teams you own, newest first, each with its agents and roles
     * nested. The list is paged with a cursor: to get the next page, send the
     * `next_cursor` of the response as `cursor`. On the last page
     * `next_cursor` is null.
     *
     * @return array{data: list<TeamData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request): array
    {
        $teams = $request->user()->workspace()
            ->teams()
            ->with(['agents', 'roles.permissions', 'roles.agents']);

        $page = CursorPage::newestFirst($teams, $request->perPage(), $request->cursor());

        return [
            'data' => array_map(TeamData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Create team
     */
    public function store(StoreApiTeamRequest $request, CreateTeamAction $action): JsonResponse
    {
        return response()->json(['data' => TeamData::fromModel($action->execute($request->toData()))], 201);
    }

    /**
     * Get team
     *
     * The team with its agents and roles nested.
     *
     * @return array{data: TeamData}
     */
    public function show(Team $team): array
    {
        $team->load(['agents', 'roles.permissions', 'roles.agents']);

        return ['data' => TeamData::fromModel($team)];
    }

    /**
     * Update team
     *
     * @return array{data: TeamData}
     */
    public function update(UpdateApiTeamRequest $request, Team $team, UpdateTeamAction $action): array
    {
        return ['data' => TeamData::fromModel($action->execute($request->toData($team)))];
    }

    /**
     * Delete team
     *
     * Deletes the team. Your primary team, your only team, and a team that
     * still has agents cannot be deleted (422).
     */
    public function destroy(Request $request, Team $team, DeleteTeamAction $deleteTeam): Response
    {
        /** @var User $user */
        $user = $request->user();

        $deleteTeam->execute($user, $team);

        return response()->noContent();
    }
}

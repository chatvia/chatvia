<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Requests\Api\V1\Agent\ListApiAgentsRequest;
use App\Http\Requests\Api\V1\Agent\StoreApiAgentRequest;
use App\Http\Requests\Api\V1\Agent\UpdateApiAgentRequest;
use App\Http\Resources\Api\V1\Agent\AgentData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Actions\Account\CreateApiAgentAction;
use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Actions\Account\UpdateApiAgentAction;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentVisibility;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Support\Pagination\CursorPage;

/**
 * The public API's agent endpoints: list, create, show, update and delete.
 * Listing uses the dashboard's visibility rule; creating uses the agent policy.
 */
#[Group(name: 'Agents', weight: 10)]
class AgentController
{
    /**
     * List agents
     *
     * Returns the agents you can access, newest first: an owner sees every
     * agent; a teammate sees their current team's granted agents. Pass
     * `?team_id=` to list only one team's agents. The list is paged with a
     * cursor: to get
     * the next page, send the `next_cursor` of the response as `cursor`. On
     * the last page `next_cursor` is null.
     *
     * @return array{data: list<AgentData>, next_cursor: string|null}
     */
    public function index(ListApiAgentsRequest $request, AgentVisibility $visibility): array
    {
        /** @var User $user */
        $user = $request->user();

        // The same visibility rule as the dashboard's agent list: owners see
        // every agent in their teams, a teammate only the agents their role
        // reaches — so a member token never lists agents it can't access.
        $query = $visibility->query($user, $user->currentOrPrimaryTeam())->with(['team', 'agentPrompt']);

        $team = $request->team();

        if ($team instanceof Team) {
            $query->where('team_id', $team->id);
        }

        $page = CursorPage::newestFirst($query, $request->perPage(), $request->cursor());

        return [
            'data' => array_map(AgentData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Create agent
     *
     * Create a new agent
     */
    public function store(StoreApiAgentRequest $request, CreateApiAgentAction $createAgent): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => AgentData::fromModel($createAgent->execute($user->workspace(), $request->toData()))], 201);
    }

    /**
     * Get agent
     *
     * @return array{data: AgentData}
     */
    public function show(Agent $agent): array
    {
        return ['data' => AgentData::fromModel($agent)];
    }

    /**
     * Update agent
     *
     * @return array{data: AgentData}
     */
    public function update(UpdateApiAgentRequest $request, Agent $agent, UpdateApiAgentAction $updateAgent): array
    {
        /** @var User $user */
        $user = $request->user();

        return ['data' => AgentData::fromModel($updateAgent->execute($user->workspace(), $agent, $request->toData()))];
    }

    /**
     * Delete agent
     *
     * The agent is gone at once: its chat address, custom domain and API
     * routes stop working right away. Its conversations, the knowledge
     * sources no other agent uses and its uploaded files are then removed in
     * the background. Account-level MCP servers and functions stay; only
     * their link to this agent is removed.
     */
    public function destroy(Agent $agent, DeleteAgentAction $deleteAgent): Response
    {
        $deleteAgent->execute($agent);

        return response()->noContent();
    }
}

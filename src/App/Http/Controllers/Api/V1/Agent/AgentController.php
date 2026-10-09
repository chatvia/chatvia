<?php

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
     * Returns every agent across all your teams. Pass `?team_id=` to list only
     * one team's agents.
     *
     * @return array{data: list<AgentData>}
     */
    public function index(ListApiAgentsRequest $request, AgentVisibility $visibility): array
    {
        /** @var User $user */
        $user = $request->user();

        // The same visibility rule as the dashboard's agent list: owners see
        // every agent in their teams, a teammate only the agents their role
        // reaches — so a member token never lists agents it can't access.
        $query = $visibility->query($user, $user->currentOrPrimaryTeam())->with(['team', 'agentPrompt'])->latest();

        $team = $request->team();

        if ($team instanceof Team) {
            $query->where('team_id', $team->id);
        }

        return ['data' => $query->get()->map(fn (Agent $agent): AgentData => AgentData::fromModel($agent))->all()];
    }

    /**
     * Create agent
     *
     * Create a new agent
     *
     * @return array{data: AgentData}
     */
    public function store(StoreApiAgentRequest $request, CreateApiAgentAction $createAgent): array
    {
        /** @var User $user */
        $user = $request->user();
        $owner = $user->workspace();

        return ['data' => AgentData::fromModel($createAgent->execute($owner, $request->toData()))];
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
     *
     *
     * @return array{data: AgentData}
     */
    public function update(UpdateApiAgentRequest $request, Agent $agent, UpdateApiAgentAction $updateAgent): array
    {
        /** @var User $user */
        $user = $request->user();
        $owner = $user->workspace();

        return ['data' => AgentData::fromModel($updateAgent->execute($owner, $agent, $request->toData()))];
    }

    /**
     * Delete agent
     *
     * Also deletes all conversations, sources, MCPs, and functions attached to
     * the agent.
     */
    public function destroy(Agent $agent, DeleteAgentAction $deleteAgent): JsonResponse
    {
        $deleteAgent->execute($agent);

        return response()->json(null, 204);
    }
}

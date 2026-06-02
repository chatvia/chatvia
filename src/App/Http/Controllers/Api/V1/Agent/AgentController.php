<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Workspace\Models\User;
use App\Http\Requests\Api\V1\Agent\StoreApiAgentRequest;
use App\Http\Requests\Api\V1\Agent\UpdateApiAgentRequest;
use App\Http\Resources\Api\V1\Agent\AgentData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Actions\Account\CreateApiAgentAction;
use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Actions\Account\UpdateApiAgentAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Customer-facing CRUD for AI agents. Only `knowledge_base` agents can be
 * provisioned via the API — widget agents must be created in the dashboard.
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
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $owner = $user->belongsToWorkspace() ? $user->workspace : $user;

        $query = Agent::query()
            ->whereIn('team_id', $owner->ownedTeams()->select('id'))
            ->with(['team', 'agentPrompt'])
            ->latest();

        if ($request->filled('team_id')) {
            $team = $owner->ownedTeams()->where('external_id', $request->query('team_id'))->first();

            if (! $team) {
                throw ValidationException::withMessages(['team_id' => 'Team not found.']);
            }

            $query->where('team_id', $team->id);
        }

        return ['data' => $query->get()->map(fn (Agent $agent): AgentData => AgentData::fromModel($agent))->all()];
    }

    /**
     * Create agent
     *
     * Provisions a new knowledge-base agent. Pass `team_id` to place it in a
     * specific team; omit it to use your primary team.
     *
     * @return array{data: AgentData}
     */
    public function store(StoreApiAgentRequest $request, CreateApiAgentAction $createAgent): array
    {
        /** @var User $user */
        $user = $request->user();
        $owner = $user->belongsToWorkspace() ? $user->workspace : $user;

        return ['data' => AgentData::fromModel($createAgent->execute($owner, $request->toData()))];
    }

    /**
     * Get agent
     *
     * @return array{data: AgentData}
     */
    public function show(Request $request, Agent $agent): array
    {
        return ['data' => AgentData::fromModel($agent)];
    }

    /**
     * Update agent
     *
     * Patch top-level agent fields. Settings (appearance, persona, etc.) use
     * the dedicated `/settings/{group}` endpoint.
     *
     * @return array{data: AgentData}
     */
    public function update(UpdateApiAgentRequest $request, Agent $agent, UpdateApiAgentAction $updateAgent): array
    {
        /** @var User $user */
        $user = $request->user();
        $owner = $user->belongsToWorkspace() ? $user->workspace : $user;

        return ['data' => AgentData::fromModel($updateAgent->execute($owner, $agent, $request->toData()))];
    }

    /**
     * Delete agent
     *
     * Also deletes all conversations, sources, MCPs, and functions attached to
     * the agent.
     */
    public function destroy(Request $request, Agent $agent, DeleteAgentAction $deleteAgent): JsonResponse
    {
        $deleteAgent->execute($agent);

        return response()->json(null, 204);
    }
}

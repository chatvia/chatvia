<?php

namespace App\Http\Controllers\Agent;

use App\Http\Requests\Agent\Crud\CreateAgentRequest;
use App\Http\Requests\Agent\Crud\UpdateAgentRequest;
use Domain\Agent\Actions\Account\CreateAgentAction;
use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Actions\Account\UpdateAgentAction;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentVisibility;
use Domain\Analytics\Queries\Agent\AgentMessageTotalsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard's agent list, plus creating, updating and deleting an agent.
 * Who may create is {@see CreateAgentRequest} (the agent policy); the
 * per-agent routes are gated by `can.access.agent` and `agent.permission:*`.
 */
class AgentController
{
    /**
     * The page, or (as JSON) the agents of the selected team the user may see.
     */
    public function index(Request $request, AgentVisibility $visibility, AgentMessageTotalsQuery $messageTotals): Response|Collection
    {
        if (! $request->wantsJson()) {
            return Inertia::render('agents/AgentsIndex');
        }

        $team = current_team();

        if (! $team) {
            return collect();
        }

        $agents = $visibility->query($request->user(), $team)
            ->where('team_id', $team->id)
            ->with(['agentPrompt:id,external_id'])
            ->withTotalMembers()
            ->latest()
            ->get();

        return AgentResourceData::listWithStats($agents, $messageTotals->get($agents->modelKeys()));
    }

    public function store(CreateAgentRequest $request, CreateAgentAction $createAgent): RedirectResponse
    {
        $agent = $createAgent->execute($request->toData());

        return to_route('agents.setup', ['agent' => $agent])
            ->with('success', __('Agent created successfully.'));
    }

    /**
     * The route-bound agent reaches the action through the request's DTO.
     */
    public function update(UpdateAgentRequest $request, Agent $agent, UpdateAgentAction $updateAgent): JsonResponse
    {
        $updateAgent->execute($request->toData());

        return response()->json(['success' => true]);
    }

    public function destroy(Agent $agent, DeleteAgentAction $deleteAgent): RedirectResponse
    {
        $deleteAgent->execute($agent);

        return redirect()->route('agents.index');
    }
}

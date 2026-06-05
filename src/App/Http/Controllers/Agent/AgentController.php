<?php

namespace App\Http\Controllers\Agent;

use App\Http\Requests\Agent\Crud\CreateAgentRequest;
use App\Http\Requests\Agent\Crud\UpdateAgentRequest;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Account\CreateAgentAction;
use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Actions\Account\UpdateAgentAction;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AgentController
{
    public function index(Request $request, TeamService $teamService): Response|Collection
    {
        if ($request->wantsJson()) {
            $team = current_team();

            if (! $team) {
                return AgentResourceData::collection(collect());
            }

            $accessibleAgentIds = $teamService->accessibleAgents($request->user(), $team)->pluck('id');

            $agents = $team->agents()
                ->whereIn('id', $accessibleAgentIds)
                ->with(['agentPrompt:id,external_id'])
                ->withStats()
                ->latest()
                ->get();

            return AgentResourceData::collection($agents);
        }

        return Inertia::render('agents/AgentsIndex');
    }

    public function store(CreateAgentRequest $request, CreateAgentAction $createAgent): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Team|null $team */
        $team = current_team();

        if (! $team) {
            abort(404, 'No team found. Please select or create a team first.');
        }

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only team owners can create agents.');
        }

        try {
            $agent = $createAgent->execute($request->toData());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withErrors(['general' => 'Failed to create agent. Please try again.'])
                ->withInput();
        }

        return to_route('agents.setup', ['agent' => $agent])
            ->with('success', 'Agent created successfully.');
    }

    public function update(UpdateAgentRequest $request, Agent $agent, UpdateAgentAction $updateAgent): JsonResponse
    {
        // Route param `agent` is read by UpdateAgentRequest::authorize/toData,
        // so accept it positionally here to keep route-model-binding wired.
        unset($agent); // unused: data flows through $request->toData()->agent

        $updateAgent->execute($request->toData());

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Agent $agent, DeleteAgentAction $deleteAgent): RedirectResponse
    {
        $user = $request->user();
        $team = current_team();

        if (! $team || ! $user->ownsTeam($team)) {
            abort(403, 'Only team owners can delete agents.');
        }

        if ($agent->team_id !== $team->id) {
            abort(404);
        }

        $deleteAgent->execute($agent);

        return redirect()->route('agents.index');
    }
}

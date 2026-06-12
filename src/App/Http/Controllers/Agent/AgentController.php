<?php

namespace App\Http\Controllers\Agent;

use App\Http\Requests\Agent\Crud\CreateAgentRequest;
use App\Http\Requests\Agent\Crud\UpdateAgentRequest;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Account\CreateAgentAction;
use Domain\Agent\Actions\Account\UpdateAgentAction;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
                ->withTotalMembers()
                ->latest()
                ->get();

            // Message totals from the rollups in one grouped query (survives retention pruning).
            $messageTotals = DB::connection(config('chatvia.analytics.connection'))
                ->table('analytics_agent_hourly_rollups')
                ->whereIn('agent_id', $agents->pluck('id'))
                ->groupBy('agent_id')
                ->selectRaw('agent_id, SUM(user_messages + assistant_messages) AS total_messages')
                ->pluck('total_messages', 'agent_id');

            $agents->each(function (Agent $agent) use ($messageTotals): void {
                $agent->setAttribute('total_messages', (int) ($messageTotals[$agent->id] ?? 0));
            });

            return AgentResourceData::collection($agents);
        }

        return Inertia::render('agents/AgentsIndex');
    }

    public function store(CreateAgentRequest $request, CreateAgentAction $createAgent): RedirectResponse
    {
        $this->ensureCurrentTeamOwnership($request->user(), 'Only team owners can create agents.');

        $agent = $createAgent->execute($request->toData());

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

    public function destroy(Request $request, Agent $agent): RedirectResponse
    {
        $team = $this->ensureCurrentTeamOwnership($request->user(), 'Only team owners can delete agents.');

        if ($agent->team_id !== $team->id) {
            abort(404);
        }

        $agent->delete();

        return redirect()->route('agents.index');
    }

    private function ensureCurrentTeamOwnership(User $user, string $forbiddenMessage): Team
    {
        $team = current_team();

        if (! $team) {
            abort(404, 'No team found. Please select or create a team first.');
        }

        if (! $user->ownsTeam($team)) {
            abort(403, $forbiddenMessage);
        }

        return $team;
    }
}

<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Data\Resources\AgentResourceData;
use App\Http\Requests\Agents\CreateAgentRequest;
use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatTopic;
use App\Teams\Models\Team;
use App\Auth\Models\User;
use Domain\Ingestion\Services\Ingestion;
use App\Prompts\PromptLoader;
use App\Hosting\Subdomain;
use App\Teams\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AgentsController
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
                ->withStats()
                ->latest()
                ->get();

            return AgentResourceData::collection($agents);
        }

        return Inertia::render('agents/AgentsIndex');
    }

    public function store(CreateAgentRequest $request, Ingestion $ingestion, Subdomain $subdomain): RedirectResponse
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
            DB::beginTransaction();

            $validated = $request->validated();
            $externalId = \Domain\Agents\Services\Agent::generateRandomExternalId();
            $agentOwnerId = $user->isManagedUser() ? $user->managed_by_user_id : $user->id;

            /** @var Agent $agent */
            $agent = Agent::create([
                'user_id' => $agentOwnerId,
                'team_id' => $team->id,
                'name' => $validated['name'],
                'sub_domain' => $validated['type'] === 'knowledge_base' ? $externalId : null,
                'system_instructions' => PromptLoader::load('knowledge/system'),
                'is_public' => false,
                'type' => $validated['type'],
                'external_id' => $externalId,
            ]);

            $agent->setSetting(key: 'collect_leads', value: true, group: 'leads');

            $agent->topics()->attach(ChatTopic::pluck('id'));

            DB::commit();

            return to_route('agents.sources', ['agent' => $agent, 'new' => true])
                ->with('success', 'Agent created successfully.');
        } catch (Throwable $exception) {
            DB::rollBack();
            report($exception);

            return back()
                ->withErrors(['general' => 'Failed to create agent. Please try again.'])
                ->withInput();
        }
    }

    public function update(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'sub_domain' => ['sometimes', 'nullable', 'string', 'min:3', 'max:255', 'alpha_dash'],
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $agent->update($validated);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Agent $agent): RedirectResponse
    {
        $user = $request->user();
        $team = current_team();

        if (! $team || ! $user->ownsTeam($team)) {
            abort(403, 'Only team owners can delete agents.');
        }

        if ($agent->team_id !== $team->id) {
            abort(404);
        }

        $agent->delete();

        return redirect()->route('agents.index');
    }

    /**
     * Rename an agent and (for knowledge-base agents) update its sub-domain.
     * Kept as a custom action because the validation/auth rules differ from
     * a generic update — only team owners may rename.
     */
    public function rename(Request $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $user = $request->user();
        $team = current_team();

        if (! $team || ! $user->ownsTeam($team)) {
            abort(403, 'Only team owners can rename agents.');
        }

        if ($agent->team_id !== $team->id) {
            abort(404);
        }

        $rules = [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('agents', 'name')
                    ->ignore($agent->id)
                    ->where('user_id', $request->user()->id),
            ],
        ];

        if ($agent->type === 'knowledge_base') {
            $rules['sub_domain'] = [
                'required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('agents', 'sub_domain')->ignore($agent->id),
            ];
        }

        $validated = $request->validate($rules);

        $updateData = ['name' => $validated['name']];

        if ($agent->type === 'knowledge_base' && isset($validated['sub_domain'])) {
            $updateData['sub_domain'] = $validated['sub_domain'];
        }

        $agent->update($updateData);

        return back()->with('success', 'Agent updated successfully');
    }
}

<?php

namespace App\Http\Controllers\Agents;

use App\Auth\Models\User;
use App\Http\Requests\Agents\CreateAgentRequest;
use App\Teams\Models\Team;
use App\Teams\Services\TeamService;
use Domain\Agents\Actions\CreateAgentAction;
use Domain\Agents\Actions\DeleteAgentAction;
use Domain\Agents\Actions\UpdateAgentAction;
use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
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
            $agent = $createAgent->execute($user, $team, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withErrors(['general' => 'Failed to create agent. Please try again.'])
                ->withInput();
        }

        return to_route('agents.sources', ['agent' => $agent, 'new' => true])
            ->with('success', 'Agent created successfully.');
    }

    public function update(Request $request, Agent $agent, UpdateAgentAction $updateAgent): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'sub_domain' => ['sometimes', 'nullable', 'string', 'min:3', 'max:255', 'alpha_dash'],
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'prompt_id' => ['sometimes', 'nullable', 'string', 'prohibits:system_instructions'],
        ]);

        $attributes = collect($validated)->except('prompt_id')->all();

        // A saved library prompt and a custom prompt are mutually exclusive:
        // whichever the request carries wins and clears the other.
        if ($request->has('prompt_id')) {
            $attributes['agent_prompt_id'] = $this->resolvePromptId($request, $validated['prompt_id'] ?? null);
            $attributes['system_instructions'] = null;
        } elseif ($request->has('system_instructions')) {
            $attributes['agent_prompt_id'] = null;
        }

        $updateAgent->execute($agent, $attributes);

        return response()->json(['success' => true]);
    }

    /**
     * Resolve a library prompt's external id to its internal id, scoped to the
     * current user. Returns null when no prompt is selected.
     */
    private function resolvePromptId(Request $request, ?string $promptExternalId): ?int
    {
        if ($promptExternalId === null || $promptExternalId === '') {
            return null;
        }

        $prompt = $request->user()->prompts()->where('external_id', $promptExternalId)->first();

        abort_if($prompt === null, 422, 'Prompt not found.');

        return $prompt->id;
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

    public function rename(Request $request, Agent $agent, UpdateAgentAction $updateAgent): RedirectResponse
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

        $updateAgent->execute($agent, $updateData);

        return back()->with('success', 'Agent updated successfully');
    }
}

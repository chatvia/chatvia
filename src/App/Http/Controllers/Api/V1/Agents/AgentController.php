<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Auth\Models\User;
use App\Http\Resources\Api\V1\AgentData;
use App\Teams\Models\Team;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Models\Agent;
use Domain\Agents\Services\Agent as AgentService;
use Domain\Chat\Models\ChatTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function store(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'prompt_id' => ['sometimes', 'nullable', 'string', 'prohibits:system_instructions'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'team_id' => ['sometimes', 'nullable', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $owner = $user->belongsToWorkspace() ? $user->workspace : $user;

        $team = $this->resolveTeam($owner, $validated['team_id'] ?? null);
        $agentPromptId = $this->resolvePromptId($owner, $validated['prompt_id'] ?? null);

        $agent = DB::transaction(function () use ($validated, $owner, $team, $agentPromptId): Agent {
            $externalId = AgentService::generateRandomExternalId();

            /** @var Agent $agent */
            $agent = Agent::create([
                'user_id' => $owner->id,
                'team_id' => $team->id,
                'name' => $validated['name'],
                'type' => 'knowledge_base',
                'sub_domain' => $externalId,
                'external_id' => $externalId,
                // A library prompt and a custom prompt are mutually exclusive.
                'system_instructions' => $agentPromptId === null ? ($validated['system_instructions'] ?? null) : null,
                'agent_prompt_id' => $agentPromptId,
                'is_public' => false,
                'analytics_enabled' => true,
                'metadata' => $validated['metadata'] ?? null,
                'created_via' => 'external',
            ]);

            $agent->topics()->attach(ChatTopic::pluck('id'));

            return $agent;
        });

        return ['data' => AgentData::fromModel($agent)];
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
    public function update(Request $request, Agent $agent): array
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'prompt_id' => ['sometimes', 'nullable', 'string', 'prohibits:system_instructions'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'analytics_enabled' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $owner = $user->belongsToWorkspace() ? $user->workspace : $user;

        $attributes = collect($validated)->except('prompt_id')->all();

        // A library prompt and a custom prompt are mutually exclusive: whichever
        // the request carries wins and clears the other.
        if ($request->has('prompt_id')) {
            $attributes['agent_prompt_id'] = $this->resolvePromptId($owner, $validated['prompt_id'] ?? null);
            $attributes['system_instructions'] = null;
        } elseif ($request->has('system_instructions')) {
            $attributes['agent_prompt_id'] = null;
        }

        $agent->update($attributes);

        return ['data' => AgentData::fromModel($agent->refresh())];
    }

    /**
     * Delete agent
     *
     * Also deletes all conversations, sources, MCPs, and functions attached to
     * the agent.
     */
    public function destroy(Request $request, Agent $agent): JsonResponse
    {
        $agent->delete();

        return response()->json(null, 204);
    }

    /**
     * Resolve the team an agent will belong to: the given owned team (by its
     * external id) or the owner's primary team when none is supplied.
     */
    private function resolveTeam(User $owner, ?string $teamExternalId): Team
    {
        if ($teamExternalId === null || $teamExternalId === '') {
            $team = $owner->primaryTeam();

            if (! $team) {
                throw ValidationException::withMessages([
                    'team_id' => 'Create a team first via POST /api/v1/teams.',
                ]);
            }

            return $team;
        }

        /** @var Team|null $team */
        $team = $owner->ownedTeams()->where('external_id', $teamExternalId)->first();

        if (! $team) {
            throw ValidationException::withMessages([
                'team_id' => 'Team not found.',
            ]);
        }

        return $team;
    }

    /**
     * Resolve a reusable prompt's id (its external id) to the internal id,
     * scoped to the owner. Returns null when no prompt id is supplied.
     */
    private function resolvePromptId(User $owner, ?string $promptExternalId): ?int
    {
        if ($promptExternalId === null || $promptExternalId === '') {
            return null;
        }

        $prompt = $owner->prompts()->where('external_id', $promptExternalId)->first();

        if (! $prompt) {
            throw ValidationException::withMessages([
                'prompt_id' => 'Prompt not found.',
            ]);
        }

        return $prompt->id;
    }
}

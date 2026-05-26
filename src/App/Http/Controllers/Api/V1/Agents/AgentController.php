<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Auth\Models\User;
use App\Http\Resources\Api\V1\AgentTransformer;
use App\Prompts\PromptLoader;
use Domain\Agents\Models\Agent;
use Domain\Agents\Services\Agent as AgentService;
use Domain\Chat\Models\ChatTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @tags Agents
 *
 * Customer-facing CRUD for AI agents. Only `knowledge_base` agents can be
 * provisioned via the API — widget agents must be created in the dashboard.
 */
class AgentController
{
    /**
     * List agents
     *
     * Returns every agent the master-token owner has access to in their
     * primary team. Agent tokens cannot reach this endpoint.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $team = $user->primaryTeam();

        if (! $team) {
            return response()->json(['data' => []]);
        }

        $agents = $team->agents()->latest()->get();

        return response()->json([
            'data' => $agents->map(fn (Agent $a): array => AgentTransformer::toArray($a))->all(),
        ]);
    }

    /**
     * Create agent
     *
     * Provisions a new knowledge-base agent under the caller's primary team.
     * The response includes the agent details and a one-time bearer token in
     * `token.plaintext` — store it immediately; it cannot be fetched later.
     *
     * End-user chat JWTs are signed with the account-wide `jwt_secret`, not a
     * per-agent secret. Generate or rotate that secret via
     * `POST /api/v1/jwt-secret` before issuing chat tokens.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'token_name' => ['sometimes', 'string', 'max:64'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $team = $user->primaryTeam();

        if (! $team) {
            throw ValidationException::withMessages([
                'team' => 'You must create a team in the dashboard before provisioning agents via API.',
            ]);
        }

        $agent = DB::transaction(function () use ($validated, $user, $team) {
            $externalId = AgentService::generateRandomExternalId();
            $ownerId = $user->isManagedUser() ? $user->managed_by_user_id : $user->id;

            /** @var Agent $agent */
            $agent = Agent::create([
                'user_id' => $ownerId,
                'team_id' => $team->id,
                'name' => $validated['name'],
                'type' => 'knowledge_base',
                'sub_domain' => $externalId,
                'external_id' => $externalId,
                'system_instructions' => $validated['system_instructions'] ?? PromptLoader::load('knowledge/system'),
                'is_public' => false,
                'collect_leads' => true,
                'analytics_enabled' => true,
                'metadata' => $validated['metadata'] ?? null,
                'created_via' => 'api',
            ]);

            $agent->topics()->attach(ChatTopic::pluck('id'));

            return $agent;
        });

        $token = $agent->createToken(
            $validated['token_name'] ?? 'Initial',
            ['agent:read', 'agent:write', 'chat:send', 'sources:write', 'mcps:write', 'functions:write'],
        );

        return response()->json([
            'agent' => AgentTransformer::toArray($agent),
            'token' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'plaintext' => $token->plainTextToken,
                'abilities' => $token->accessToken->abilities,
            ],
        ], 201);
    }

    /**
     * Get agent
     */
    public function show(Request $request, Agent $agent): JsonResponse
    {
        return response()->json(['agent' => AgentTransformer::toArray($agent)]);
    }

    /**
     * Update agent
     *
     * Patch top-level agent fields. Settings (appearance, persona, etc.) use
     * the dedicated `/settings/{group}` endpoint.
     */
    public function update(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'collect_leads' => ['sometimes', 'boolean'],
            'analytics_enabled' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $agent->update($validated);

        return response()->json(['agent' => AgentTransformer::toArray($agent->refresh())]);
    }

    /**
     * Delete agent
     *
     * Cascades: deletes all tokens, conversations, sources, MCPs, and functions
     * attached to the agent.
     */
    public function destroy(Request $request, Agent $agent): JsonResponse
    {
        $agent->tokens()->delete();
        $agent->delete();

        return response()->json(null, 204);
    }
}

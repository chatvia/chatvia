<?php

namespace App\Http\Controllers\Api\V1\Agents;

use Domain\Agents\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @tags Agent Tokens
 *
 * Manage bearer tokens for an agent. Plaintext is shown ONCE on creation
 * or rotation; afterwards only the metadata (id, name, abilities,
 * last_used_at) is visible.
 */
class AgentTokenController
{
    /**
     * List tokens
     *
     * Returns metadata for all tokens issued for this agent. Plaintext is
     * never returned again after creation.
     */
    public function index(Request $request, Agent $agent): JsonResponse
    {
        $tokens = $agent->tokens()->latest()->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']);

        return response()->json([
            'data' => $tokens->map(fn ($t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Create token
     *
     * Mints a new bearer token. The plaintext is in the response — store it
     * immediately. Default abilities cover all per-agent operations; pass
     * `abilities` to issue a scoped token (e.g. chat-only).
     */
    public function store(Request $request, Agent $agent): JsonResponse
    {
        $defaultAbilities = ['agent:read', 'agent:write', 'chat:send', 'sources:write', 'mcps:write', 'functions:write'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', 'in:'.implode(',', $defaultAbilities)],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        $token = $agent->createToken(
            $validated['name'],
            $validated['abilities'] ?? $defaultAbilities,
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
        );

        return response()->json([
            'token' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'plaintext' => $token->plainTextToken,
                'abilities' => $token->accessToken->abilities,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Revoke token
     */
    public function destroy(Request $request, Agent $agent, int $tokenId): JsonResponse
    {
        $agent->tokens()->whereKey($tokenId)->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Auth\Services\AgentJwtIssuer;
use Domain\Agents\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Chat Tokens
 *
 * Mint a short-lived JWT identifying one of your end-users to a specific
 * agent's chat. Use this when you'd rather not import a JWT library on your
 * backend — Chatvia signs the JWT for you with the agent's `jwt_secret`.
 *
 * The returned `chat_url` already has the token appended; you can redirect
 * the user to it directly:
 *
 *     res.redirect(response.chat_url);
 *
 * The token is opaque from your perspective. Treat it like a one-time
 * password: short-lived (default 15 min, max 1 hour) and tied to one user's
 * session start. Subsequent page loads on the chat host reuse a session
 * cookie, so you don't mint a new token per request — only per chat-session
 * start.
 */
class AgentChatTokenController
{
    public function __construct(
        private readonly AgentJwtIssuer $issuer,
    ) {}

    /**
     * Mint chat token
     *
     * Returns a signed JWT plus the ready-to-redirect chat URL.
     *
     * Auth: master token (`agents:manage`) or agent token (`chat:send`).
     */
    public function store(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'sub' => ['required', 'string', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'ttl_seconds' => ['sometimes', 'integer', 'min:'.AgentJwtIssuer::MIN_TTL_SECONDS, 'max:'.AgentJwtIssuer::MAX_TTL_SECONDS],
        ]);

        $result = $this->issuer->issue(
            agent: $agent,
            sub: $validated['sub'],
            name: $validated['name'] ?? null,
            email: $validated['email'] ?? null,
            metadata: $validated['metadata'] ?? [],
            ttlSeconds: $validated['ttl_seconds'] ?? AgentJwtIssuer::DEFAULT_TTL_SECONDS,
        );

        return response()->json([
            'token' => $result['token'],
            'chat_url' => $agent->chat_url.'?token='.$result['token'],
            'expires_at' => $result['expires_at']->toIso8601String(),
        ], 201);
    }
}

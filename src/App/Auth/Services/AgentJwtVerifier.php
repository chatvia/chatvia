<?php

namespace App\Auth\Services;

use Domain\Agents\Models\Agent;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Verifies HS256 JWTs that the customer's backend signs with their account's
 * `jwt_secret` (held on the owning [[App\Auth\Models\User]]) to identify
 * end-users hitting the chat URL.
 *
 * Expected payload shape — all claims optional except `sub` and `exp`:
 *
 *   {
 *     "sub":   "<customer's user id>",
 *     "exp":   <unix seconds>,
 *     "iat":   <unix seconds>,
 *     "email": "user@example.com",
 *     "name":  "Jane Doe",
 *     "metadata": { ... arbitrary, surfaced to webhooks ... }
 *   }
 *
 * Returns null on any verification failure (bad signature, missing claims,
 * expired, no secret configured) so middleware can decide how to react.
 */
class AgentJwtVerifier
{
    /**
     * @return array{sub: string, email?: string|null, name?: string|null, metadata?: array<string, mixed>}|null
     */
    public function verify(Agent $agent, string $token): ?array
    {
        $secret = $agent->user->jwt_secret;

        if (! $secret) {
            return null;
        }

        try {
            $decoded = (array) JWT::decode($token, new Key($secret, 'HS256'));
        } catch (Throwable) {
            return null;
        }

        if (! isset($decoded['sub']) || $decoded['sub'] === '') {
            return null;
        }

        return [
            'sub' => (string) $decoded['sub'],
            'email' => isset($decoded['email']) ? (string) $decoded['email'] : null,
            'name' => isset($decoded['name']) ? (string) $decoded['name'] : null,
            'metadata' => isset($decoded['metadata']) ? (array) $decoded['metadata'] : [],
        ];
    }
}

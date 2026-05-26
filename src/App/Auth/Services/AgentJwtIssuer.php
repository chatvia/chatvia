<?php

namespace App\Auth\Services;

use Domain\Agents\Models\Agent;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Issues HS256 JWTs signed with an account's `jwt_secret` so customers can
 * mint chat-session tokens via the API instead of pulling in a JWT library
 * on their side. Mirror of [[AgentJwtVerifier]] — same claim shape, opposite
 * direction.
 *
 * The secret is held at the User level and shared across every agent that
 * user owns; one rotation invalidates JWTs for all of them at once.
 */
class AgentJwtIssuer
{
    public const MIN_TTL_SECONDS = 60;          // anything shorter is unusable in practice

    public const DEFAULT_TTL_SECONDS = 900;     // 15 minutes

    public const MAX_TTL_SECONDS = 3600;        // 1 hour — beyond this, sign client-side

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{token: string, expires_at: Carbon}
     */
    public function issue(
        Agent $agent,
        string $sub,
        ?string $name = null,
        ?string $email = null,
        array $metadata = [],
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): array {
        $secret = $agent->user->jwt_secret;

        if (! $secret) {
            throw new InvalidArgumentException('Account has no jwt_secret. Generate one via POST /api/v1/jwt-secret first.');
        }

        if ($ttlSeconds < self::MIN_TTL_SECONDS || $ttlSeconds > self::MAX_TTL_SECONDS) {
            throw new InvalidArgumentException('ttl_seconds must be between '.self::MIN_TTL_SECONDS.' and '.self::MAX_TTL_SECONDS.'.');
        }

        $now = Carbon::now();
        $expiresAt = $now->copy()->addSeconds($ttlSeconds);

        $payload = array_filter([
            'sub' => $sub,
            'name' => $name,
            'email' => $email,
            'metadata' => $metadata !== [] ? $metadata : null,
            'iat' => $now->timestamp,
            'exp' => $expiresAt->timestamp,
        ], fn ($v): bool => $v !== null);

        $token = JWT::encode($payload, $secret, 'HS256');

        return ['token' => $token, 'expires_at' => $expiresAt];
    }
}

<?php

namespace App\Auth\Services;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Mints short-lived sign-in tokens that log an agent's end-user into its chat.
 * A token is an opaque random string mapped in the cache to the (agent, user)
 * it was minted for. It is NOT consumed on use — the same link works on repeat
 * opens and survives browser prefetch / double requests; the TTL (default 1
 * hour) is the only thing that invalidates it.
 */
class AgentSignInTokenService
{
    private const PREFIX = 'agent-signin:';

    public const DEFAULT_TTL_SECONDS = 3600;

    public const MIN_TTL_SECONDS = 60;

    public const MAX_TTL_SECONDS = 3600;

    /**
     * @return array{token: string, expires_at: Carbon}
     */
    public function generate(Agent $agent, User $user, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): array
    {
        $token = Str::random(48);
        $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

        Cache::put(self::PREFIX.$token, ['agent_id' => $agent->id, 'user_id' => $user->id], $expiresAt);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Resolve a token to the {agent_id, user_id} it was minted for, or null when
     * unknown or expired. The token is left in place until its TTL lapses.
     *
     * @return array{agent_id: int, user_id: int}|null
     */
    public function verify(string $token): ?array
    {
        $payload = Cache::get(self::PREFIX.$token);

        if (! is_array($payload) || ! isset($payload['agent_id'], $payload['user_id'])) {
            return null;
        }

        return ['agent_id' => (int) $payload['agent_id'], 'user_id' => (int) $payload['user_id']];
    }
}

<?php

namespace App\Auth\Services;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatterAuthTokenService
{
    /**
     * Generate and cache an authentication token for a user to access an agent.
     */
    public function generateToken(Agent $agent, User $user, int $ttlMinutes = 60): string
    {
        $authToken = $this->createTokenString($agent, $user);

        $this->cacheToken($authToken, $user, $agent, $ttlMinutes);

        return $authToken;
    }

    /**
     * Create the token string format.
     */
    private function createTokenString(Agent $agent, User $user): string
    {
        return Str::uuid()->toString().'-'.$agent->id.'-'.$user->id;
    }

    /**
     * Cache the token with user and agent data.
     */
    private function cacheToken(string $token, User $user, Agent $agent, int $ttlMinutes): void
    {
        Cache::put($token, [
            'user_id' => $user->id,
            'agent_id' => $agent->id,
        ], now()->addMinutes($ttlMinutes));
    }

    /**
     * Retrieve cached token data.
     */
    public function getTokenData(string $token): ?array
    {
        return Cache::get($token);
    }

    /**
     * Invalidate a token by removing it from cache.
     */
    public function invalidateToken(string $token): void
    {
        Cache::forget($token);
    }
}

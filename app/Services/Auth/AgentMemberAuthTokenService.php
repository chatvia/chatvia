<?php

namespace App\Services\Auth;

use App\Models\Agent;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AgentMemberAuthTokenService
{
    /**
     * Generate and cache an authentication token for a member to access an agent.
     */
    public function generateToken(Agent $agent, Member $member, int $ttlMinutes = 60): string
    {
        $authToken = $this->createTokenString($agent, $member);

        $this->cacheToken($authToken, $member, $agent, $ttlMinutes);

        return $authToken;
    }

    /**
     * Generate and cache an authentication token from a user (gets memberOwner).
     */
    public function generateTokenFromUser(Agent $agent, User $user, int $ttlMinutes = 60): string
    {
        $member = $user->memberOwner()->firstOrCreate(
            ['is_owner' => true],
            [
                'name' => $user->name,
                'email' => $user->email,
                'password' => Hash::make(Str::random()),
                'email_verified_at' => now(),
            ]
        );

        return $this->generateToken($agent, $member, $ttlMinutes);
    }

    /**
     * Create the token string format.
     */
    private function createTokenString(Agent $agent, Member $member): string
    {
        return Str::uuid()->toString().'-'.$agent->id.'-'.$member->id;
    }

    /**
     * Cache the token with member and agent data.
     */
    private function cacheToken(string $token, Member $member, Agent $agent, int $ttlMinutes): void
    {
        Cache::put($token, [
            'member_id' => $member->id,
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

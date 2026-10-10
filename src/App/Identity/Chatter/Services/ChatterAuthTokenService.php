<?php

namespace App\Identity\Chatter\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One-time tokens that sign a user in to one agent's hosted chat
 * (`/chat/{agentSlug}?auth_token=…`). They carry a sign-in from outside the
 * chat session — a dashboard preview, an accepted invitation, an emailed
 * sign-in link — into the chat's own session.
 *
 * A token is cached under its own key prefix, so no other cached value (such
 * as a widget session) can ever be used as one. It works only for the agent it
 * was minted for, and only once.
 */
class ChatterAuthTokenService
{
    private const string PREFIX = 'chat-handoff:';

    /**
     * Mint and cache a token that signs $user in to $agent's chat.
     */
    public function generateToken(Agent $agent, User $user, int $ttlMinutes = 60): string
    {
        $token = Str::random(48);

        Cache::put(self::PREFIX.$token, [
            'user_id' => $user->id,
            'agent_id' => $agent->id,
        ], now()->addMinutes($ttlMinutes));

        return $token;
    }

    /**
     * Use up a token for $agent: returns the id of the user it signs in, or
     * null when it is unknown, expired, already used or minted for another
     * agent. A token presented for the wrong agent is used up too.
     */
    public function consume(string $token, Agent $agent): ?int
    {
        if ($token === '') {
            return null;
        }

        $data = Cache::pull(self::PREFIX.$token);

        if (! is_array($data) || ($data['agent_id'] ?? null) !== $agent->id) {
            return null;
        }

        return (int) $data['user_id'];
    }
}

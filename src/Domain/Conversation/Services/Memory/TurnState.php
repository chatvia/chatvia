<?php

namespace Domain\Conversation\Services\Memory;

use Domain\Agent\Models\Agent;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class TurnState
{
    private const string SESSION_KEY = 'cs:session';

    private const int GUEST_SESSION_TTL_DAYS = 3;

    public function firstOrCreateSession(Agent $agent): string
    {
        $existingSessionId = Session::get(self::SESSION_KEY);

        if ($existingSessionId && $this->isValidSession($existingSessionId)) {
            return $existingSessionId;
        }

        // ChatLogoutController invalidates the Laravel session, which drops
        // cs:session — without reattaching here every relogin would fork a
        // fresh chat_sessions row and orphan the chatter's prior conversations.
        if ($this->isMemberAuth()) {
            $existing = \Domain\Conversation\Models\Session::query()
                ->where('user_id', Auth::guard('web')->id())
                ->where('agent_id', $agent->id)
                ->latest('id')
                ->first();

            if ($existing) {
                Session::put(self::SESSION_KEY, $existing->external_id);

                return $existing->external_id;
            }
        }

        $session = \Domain\Conversation\Models\Session::create([
            'agent_id' => $agent->id,
            'user_id' => Auth::guard('web')->id(),
        ]);

        $this->initializeSession($session->external_id);

        return $session->external_id;
    }

    public function isMemberAuth(): bool
    {
        return Auth::guard('web')->check();
    }

    private function isValidSession(string $sessionId): bool
    {
        return $this->getChatCache($sessionId)->has($sessionId);
    }

    private function initializeSession(string $sessionId): void
    {
        $sessionData = ['conversations' => []];

        if ($this->isMemberAuth()) {
            $this->getChatCache($sessionId)->forever($sessionId, $sessionData);
        } else {
            $this->getChatCache($sessionId)->put(
                key: $sessionId,
                value: $sessionData,
                ttl: now()->addDays(self::GUEST_SESSION_TTL_DAYS)
            );
        }

        Session::put(self::SESSION_KEY, $sessionId);
    }

    public function deleteSession($sessionId): void
    {
        $this->getChatCache($sessionId)->forget($sessionId);
    }

    private function getChatCache($sessionId): Repository
    {
        return Cache::driver('chat')->tags($sessionId);
    }
}

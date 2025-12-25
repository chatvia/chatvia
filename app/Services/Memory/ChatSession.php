<?php

namespace App\Services\Memory;

use App\Models\Agent;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ChatSession
{
    private const string SESSION_KEY = 'cs:session';

    private const int GUEST_SESSION_TTL_DAYS = 3;

    public function firstOrCreateChatSession(Agent $agent): string
    {
        $existingSessionId = Session::get(self::SESSION_KEY);

        if ($existingSessionId && $this->isValidSession($existingSessionId)) {
            return $existingSessionId;
        }

        $session = \App\Models\ChatSession::create(['agent_id' => $agent->id]);

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

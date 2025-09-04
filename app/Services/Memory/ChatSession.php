<?php

namespace App\Services\Memory;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ChatSession
{
    private const string SESSION_KEY = 'cs:session';

    private const int GUEST_SESSION_TTL_DAYS = 3;

    public function firstOrCreateChatSession(): string
    {
        $existingSessionId = Session::get(self::SESSION_KEY);

        if ($existingSessionId && $this->isValidSession($existingSessionId)) {
            return $existingSessionId;
        }

        $newSessionId = $this->generateSessionKey();

        $this->initializeSession($newSessionId);

        return $newSessionId;
    }

    public function generateSessionKey(): string
    {
        return Str::uuid();
    }

    public function isMemberAuth(): bool
    {
        return Auth::guard('member')->check();
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

    private function getChatCache($sessionId): Repository
    {
        return Cache::driver('chat')->tags($sessionId);
    }
}

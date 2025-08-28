<?php

namespace App\Services\Memory;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ChatSession
{
    private const string CHAT_SESSION_PREFIX = 'chat-session';

    private const string SESSION_KEY = self::CHAT_SESSION_PREFIX;

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

    public function generateSessionKey(string $prefix = ''): string
    {
        if ($prefix) {
            return self::CHAT_SESSION_PREFIX.':'.$prefix.':'.Str::uuid();
        }

        if ($this->isMemberAuth()) {
            return self::CHAT_SESSION_PREFIX.':member:'.Auth::guard('member')->id().':'.Str::uuid();
        }

        return self::CHAT_SESSION_PREFIX.':guest:'.Str::uuid();
    }

    public function isMemberAuth(): bool
    {
        return Auth::guard('member')->check();
    }

    public function member()
    {
        return Auth::guard('member')->user();
    }

    public function destroySession(): void
    {
        $sessionId = Session::get(self::SESSION_KEY);

        if ($sessionId) {
            $this->getChatCache()->forget($sessionId);
            Session::forget(self::SESSION_KEY);
        }
    }

    private function isValidSession(string $sessionId): bool
    {
        return $this->getChatCache()->has($sessionId);
    }

    private function initializeSession(string $sessionId): void
    {
        $sessionData = ['conversations' => []];

        if ($this->isMemberAuth()) {
            $this->getChatCache()->forever($sessionId, $sessionData);
        } else {
            $this->getChatCache()->put(
                key: $sessionId,
                value: $sessionData,
                ttl: now()->addDays(self::GUEST_SESSION_TTL_DAYS)
            );
        }

        Session::put(self::SESSION_KEY, $sessionId);
    }

    private function getChatCache(): Repository
    {
        return Cache::driver('chat');
    }
}

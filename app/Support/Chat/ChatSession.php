<?php

namespace App\Support\Chat;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatSession
{
    private const CHAT_SESSION_PREFIX = 'chat-session:';

    public function getOrGenerateSession(): string
    {
        $chatSession = request()->session()->get(self::CHAT_SESSION_PREFIX);

        if ($chatSession) {
            return $chatSession;
        }

        $chatSessionId = $this->generateSessionId();

        if ($this->isUserAuth()) {

            if ($chatSession = Cache::get($chatSessionId)) {
                request()->session()->put(self::CHAT_SESSION_PREFIX, $chatSession);

                return $chatSession;
            }

            request()->session()->put(self::CHAT_SESSION_PREFIX, $chatSession);

            Cache::forever($chatSessionId, ['conversations' => []]);

            return $chatSession;
        }

        Cache::put(
            key: $chatSessionId,
            value: ['conversations' => []],
            ttl: now()->addDays(3)
        );

        request()->session()->put(self::CHAT_SESSION_PREFIX, $chatSessionId);

        return $chatSessionId;
    }

    public function generateSessionId(): string
    {
        if ($this->isUserAuth()) {
            return 'chat-session.user.'.Auth::id();
        }

        return 'chat-session.guest.'.Str::uuid()->toString();
    }

    public function isUserAuth(): bool
    {
        return Auth::check();
    }
}

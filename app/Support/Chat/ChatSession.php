<?php

namespace App\Support\Chat;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ChatSession
{
    private const string CHAT_SESSION_PREFIX = 'chat-session';

    public function getOrGenerateSession(bool $playground = false): string
    {
        // Here, we're storing the chat session ID in the session storage, and also in the cache storage.
        // The session id will be used to access the chat session data in the cache storage.

        $existingChatSessionId = Session::get(self::CHAT_SESSION_PREFIX);

        if ($existingChatSessionId) {
            return $existingChatSessionId;
        }

        $newChatSessionId = $this->generateSessionId($playground);

        if ($this->isMemberAuth()) {

            if ($this->getChatCache()->has($newChatSessionId)) {
                Session::put(self::CHAT_SESSION_PREFIX, $newChatSessionId);

                return $newChatSessionId;
            }

            $this->getChatCache()->forever($newChatSessionId, ['conversations' => []]);

            Session::put(self::CHAT_SESSION_PREFIX, $newChatSessionId);

            return $newChatSessionId;
        }

        $this->getChatCache()->put(
            key: $newChatSessionId,
            value: ['conversations' => []],
            ttl: now()->addDays(3),
        );

        Session::put(self::CHAT_SESSION_PREFIX, $newChatSessionId);

        return $newChatSessionId;
    }

    public function generateSessionId(bool $playground = false): string
    {
        if ($playground) {
            return 'chat-session.playground.'.Str::uuid()->toString();
        }

        if ($this->isMemberAuth()) {
            return 'chat-session.member.'.Auth::guard('member')->id();
        }

        return 'chat-session.guest.'.Str::uuid()->toString();
    }

    public function isMemberAuth(): bool
    {
        return Auth::guard('member')->check();
    }

    public function member()
    {
        return Auth::guard('member')->user();
    }

    public function getChatCache()
    {
        return Cache::driver('chat');
    }
}

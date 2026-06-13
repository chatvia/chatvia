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
        $workspaceExternalId = $agent->workspaceExternalId();
        $agentExternalId = $agent->external_id;

        $existingSessionId = Session::get(self::SESSION_KEY);

        if ($existingSessionId && $this->isValidSession($existingSessionId, $workspaceExternalId, $agentExternalId)) {
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

        $this->initializeSession($session->external_id, $workspaceExternalId, $agentExternalId);

        return $session->external_id;
    }

    public function isMemberAuth(): bool
    {
        return Auth::guard('web')->check();
    }

    private function isValidSession(string $sessionId, string $workspaceExternalId, string $agentExternalId): bool
    {
        return $this->getChatCache($sessionId, $workspaceExternalId, $agentExternalId)->has($sessionId);
    }

    private function initializeSession(string $sessionId, string $workspaceExternalId, string $agentExternalId): void
    {
        $sessionData = ['conversations' => []];

        if ($this->isMemberAuth()) {
            $this->getChatCache($sessionId, $workspaceExternalId, $agentExternalId)->forever($sessionId, CacheCipher::encrypt($sessionData));
        } else {
            $this->getChatCache($sessionId, $workspaceExternalId, $agentExternalId)->put(
                key: $sessionId,
                value: CacheCipher::encrypt($sessionData),
                ttl: now()->addDays(self::GUEST_SESSION_TTL_DAYS)
            );
        }

        Session::put(self::SESSION_KEY, $sessionId);
    }

    /**
     * Session-scoped tagged cache. The tag set is the shared session prefix
     * from {@see ChatCache::sessionTags()} — `[workspaceTag, agentTag,
     * sessionId]`, in that order — because this touches the same session-data
     * key as {@see ChatCache::tagsFor()} with a null conversation. Tagged-cache
     * reads hash the full ordered tag set, so any divergence (different tags or
     * order) yields a different namespace and the read silently misses.
     */
    private function getChatCache(string $sessionId, string $workspaceExternalId, string $agentExternalId): Repository
    {
        return Cache::driver('chat')->tags(ChatCache::sessionTags($workspaceExternalId, $agentExternalId, $sessionId));
    }
}

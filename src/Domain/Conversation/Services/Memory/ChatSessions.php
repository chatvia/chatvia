<?php

namespace Domain\Conversation\Services\Memory;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;

/**
 * Read side of visitor sessions for the hosted chat: whether a remembered
 * session id is still live in the chat memory, and the latest session a
 * signed-in chatter had with an agent (so a fresh browser session after
 * signing out and in again reattaches to their history instead of starting
 * over).
 */
class ChatSessions
{
    /** True while the session's entry is still in the live chat memory. */
    public function isLive(Agent $agent, string $sessionExternalId): bool
    {
        return new ChatCache($sessionExternalId, $agent)->has($sessionExternalId);
    }

    /** The user's most recent session with the agent, or null when they have none. */
    public function latestFor(Agent $agent, int $userId): ?string
    {
        return Session::query()
            ->where('user_id', $userId)
            ->where('agent_id', $agent->id)
            ->latest('id')
            ->value('external_id');
    }
}

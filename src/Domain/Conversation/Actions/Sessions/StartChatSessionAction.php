<?php

namespace Domain\Conversation\Actions\Sessions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;

/**
 * Starts a new visitor session with an agent: the session row, and an empty
 * conversation list in the live chat memory. A signed-in chatter's session is
 * kept until they delete it; a guest's lapses after a few days, after which
 * the guest simply gets a new session.
 */
class StartChatSessionAction
{
    /** How long a guest's session stays live in the chat memory. */
    public const int GUEST_SESSION_TTL_DAYS = 3;

    /**
     * @param  int|null  $userId  the signed-in user the session belongs to, or null for a guest
     * @return string the new session's external id
     */
    public function execute(Agent $agent, ?int $userId): string
    {
        $session = Session::create([
            'agent_id' => $agent->id,
            'user_id' => $userId,
        ]);

        new ChatCache($session->external_id, $agent)->putUntil(
            $session->external_id,
            ['conversations' => []],
            $userId !== null ? null : now()->addDays(self::GUEST_SESSION_TTL_DAYS),
        );

        return $session->external_id;
    }
}

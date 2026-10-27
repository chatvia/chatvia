<?php

namespace Domain\Conversation\Actions\Sessions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;

/**
 * Starts a new visitor session with an agent: the session row, and an empty
 * conversation list in the live chat memory. A signed-in viewer's session
 * (chatter or staff) is kept until they delete it; a guest's lapses after a few days, after which
 * the guest simply gets a new session.
 */
class StartChatSessionAction
{
    /** How long a guest's session stays live in the chat memory. */
    public const int GUEST_SESSION_TTL_DAYS = 3;

    /**
     * @param  ChatViewerData|null  $viewer  the signed-in chatter or staff account the session belongs to, or null for a guest
     * @return string the new session's external id
     */
    public function execute(Agent $agent, ?ChatViewerData $viewer): string
    {
        $session = Session::query()->create([
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
            'user_id' => $viewer instanceof ChatViewerData && ! $viewer->isChatter() ? $viewer->id : null,
            'chatter_id' => $viewer?->isChatter() ? $viewer->id : null,
        ]);

        new ChatCache($session->external_id, $agent)->putUntil(
            $session->external_id,
            ['conversations' => []],
            $viewer instanceof ChatViewerData ? null : now()->addDays(self::GUEST_SESSION_TTL_DAYS),
        );

        return $session->external_id;
    }
}

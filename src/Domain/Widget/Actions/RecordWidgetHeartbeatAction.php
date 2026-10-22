<?php

namespace Domain\Widget\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Activity\TouchConversationActivityAction;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One "still here" ping from an open widget. Every open tab pings every 30
 * seconds, and the activity stamp moves at most once a minute anyway, so a
 * cache key lets one ping a minute per visitor and conversation reach the
 * database; the others return at once, without a query. A ping that gets
 * through checks that the conversation is the visitor's, then moves its
 * activity time ({@see TouchConversationActivityAction}).
 *
 * Returns false when the conversation is not the visitor's.
 */
class RecordWidgetHeartbeatAction
{
    /** How long one checked ping covers the pings after it. */
    public const int GATE_SECONDS = 60;

    public function __construct(
        private readonly FindWidgetConversationAction $findConversation,
        private readonly TouchConversationActivityAction $touchActivity,
    ) {}

    public function execute(Agent $agent, string $conversationExternalId, string $sessionExternalId, ?ChatViewerData $viewer = null): bool
    {
        // External ids are UUIDs: anything else can never match, and a
        // client-sent string of any length never becomes a cache key.
        if (! Str::isUuid($conversationExternalId) || ! Str::isUuid($sessionExternalId)) {
            return false;
        }

        // The viewer is part of the key: a ping of one chatter never covers
        // another chatter's ping with the same ids.
        $gate = "widget:hb:{$agent->id}:{$sessionExternalId}:{$conversationExternalId}:".($viewer?->key() ?? 'visitor');

        // A ping in the last minute already checked this conversation.
        if (! Cache::add($gate, true, self::GATE_SECONDS)) {
            return true;
        }

        // The conversation must belong to this session and agent, or anyone
        // with a guessed id could move another visitor's activity time.
        $conversation = $this->findConversation->execute($conversationExternalId, $sessionExternalId, $agent, $viewer);

        if (! $conversation instanceof Conversation) {
            // So the next ping with these ids is checked (and refused) again.
            Cache::forget($gate);

            return false;
        }

        $this->touchActivity->execute($conversation);

        return true;
    }
}

<?php

namespace Domain\Conversation\Data\Memory;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;

/**
 * Which visitor session an operation acts on — and, when set, which of its
 * conversations — plus who is looking at it.
 *
 * `viewer` is the signed-in chatter or staff account on the hosted chat, or
 * null for anonymous visitors, the widget and the messaging channels. The
 * cached conversation list is stamped with it, and conversation ownership
 * markers are keyed by it, so it must be resolved by the HTTP layer and passed
 * in — the domain never reads the auth guard itself.
 */
class ChatScopeData extends Data
{
    public function __construct(
        public Agent $agent,
        public string $sessionId,
        public ?string $conversationId = null,
        public ?ChatViewerData $viewer = null,
    ) {}

    /** The same session and viewer, narrowed to one conversation. */
    public function forConversation(string $conversationId): self
    {
        return new self($this->agent, $this->sessionId, $conversationId, $this->viewer);
    }
}

<?php

namespace Domain\Channel\Concerns;

use Domain\Channel\Contracts\ChannelConversationMapping;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The shared half of every channel mapping model ({@see ChannelConversationMapping}):
 * the Session + Conversation relations behind the `chat_session_id` /
 * `chat_conversation_id` columns every mapping table carries, and the stamp
 * written after each reply.
 */
trait MapsChannelConversation
{
    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'chat_session_id');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'chat_conversation_id');
    }

    public function sessionExternalId(): string
    {
        return $this->session->external_id;
    }

    public function conversationExternalId(): string
    {
        return $this->conversation->external_id;
    }

    public function markReplied(): void
    {
        $this->forceFill(['last_message_at' => now()])->save();
    }
}

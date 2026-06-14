<?php

namespace Domain\Channel\Instagram\Models;

use Domain\Channel\Contracts\ChannelConversationMapping;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Maps an Instagram Direct thread (account + Instagram-scoped user id) to the
 * persistent Chatvia Session + Conversation behind it, so an Instagram user
 * keeps one ongoing conversation (and its history) across messages.
 *
 * @property string $igsid
 * @property int $chat_session_id
 * @property int $chat_conversation_id
 * @property string|null $name
 * @property Carbon|null $last_message_at
 */
#[Guarded(['id'])]
class InstagramThread extends Model implements ChannelConversationMapping
{
    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AgentInstagramAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(AgentInstagramAccount::class, 'agent_instagram_account_id');
    }

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
}

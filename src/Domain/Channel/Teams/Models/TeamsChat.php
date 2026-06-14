<?php

namespace Domain\Channel\Teams\Models;

use Domain\Channel\Contracts\ChannelConversationMapping;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Maps a Teams conversation (bot + Teams conversation id — a personal chat,
 * group chat, or channel thread) to the persistent Chatvia Session +
 * Conversation behind it, so a Teams chat keeps one ongoing conversation (and
 * its history) across messages. `service_url` is the regional Bot Framework
 * host replies go to; it arrives on every activity and is kept fresh.
 *
 * @property string $conversation_id
 * @property string $service_url
 * @property string|null $conversation_type
 * @property int $chat_session_id
 * @property int $chat_conversation_id
 * @property string|null $peer_name
 * @property Carbon|null $last_message_at
 */
#[Guarded(['id'])]
class TeamsChat extends Model implements ChannelConversationMapping
{
    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AgentTeamsBot, $this>
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(AgentTeamsBot::class, 'agent_teams_bot_id');
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

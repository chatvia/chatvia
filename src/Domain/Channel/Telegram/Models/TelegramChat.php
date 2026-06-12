<?php

namespace Domain\Channel\Telegram\Models;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps one Telegram chat (a bot + the Telegram `chat_id`) to the Chatvia Session
 * + Conversation that hold its history, so a Telegram user keeps context across
 * messages. Created on first contact.
 *
 * @property string $chat_id
 * @property int $chat_session_id
 * @property int $chat_conversation_id
 */
#[Guarded(['id'])]
class TelegramChat extends Model
{
    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AgentTelegramBot, $this>
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(AgentTelegramBot::class, 'agent_telegram_bot_id');
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

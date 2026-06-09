<?php

namespace Domain\Channel\Telegram\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps one Telegram chat (a bot + the Telegram `chat_id`) to the Chatvia Session
 * + Conversation that hold its history, so a Telegram user keeps context across
 * messages. Created on first contact.
 *
 * @property string $chat_id
 * @property string $session_external_id
 * @property string $conversation_external_id
 */
class TelegramChat extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(AgentTelegramBot::class, 'agent_telegram_bot_id');
    }
}

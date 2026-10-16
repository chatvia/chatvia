<?php

namespace Domain\Channel\Telegram\Models;

use Domain\Channel\Concerns\MapsChannelConversation;
use Domain\Channel\Contracts\ChannelConversationMapping;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps one Telegram chat (a bot + the Telegram `chat_id`) to the Chatvia Session
 * + Conversation that hold its history, so a Telegram user keeps context across
 * messages. Created on first contact.
 *
 * @property string $chat_id
 * @property int $workspace_id
 * @property int $chat_session_id
 * @property int $chat_conversation_id
 */
#[Guarded(['id'])]
class TelegramChat extends Model implements ChannelConversationMapping
{
    use MapsChannelConversation;

    /**
     * @return BelongsTo<AgentTelegramBot, $this>
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(AgentTelegramBot::class, 'agent_telegram_bot_id');
    }

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }
}

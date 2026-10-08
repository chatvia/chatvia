<?php

namespace Domain\Channel\Whatsapp\Models;

use Domain\Channel\Contracts\ChannelConversationMapping;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Maps a WhatsApp chat (business number + the user's wa_id) to the persistent
 * Chatvia Session + Conversation behind it, so a WhatsApp user keeps one
 * ongoing conversation (and its history) across messages.
 *
 * @property string $wa_id
 * @property int $chat_session_id
 * @property int $chat_conversation_id
 * @property string|null $name
 * @property Carbon|null $last_message_at
 */
#[Guarded(['id'])]
class WhatsappChat extends Model implements ChannelConversationMapping
{
    /**
     * @return BelongsTo<AgentWhatsappNumber, $this>
     */
    public function number(): BelongsTo
    {
        return $this->belongsTo(AgentWhatsappNumber::class, 'agent_whatsapp_number_id');
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

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }
}

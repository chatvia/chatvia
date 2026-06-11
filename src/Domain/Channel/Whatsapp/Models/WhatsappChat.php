<?php

namespace Domain\Channel\Whatsapp\Models;

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
 * @property string $session_external_id
 * @property string $conversation_external_id
 * @property string|null $name
 * @property Carbon|null $last_message_at
 */
#[Guarded(['id'])]
class WhatsappChat extends Model
{
    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AgentWhatsappNumber, $this>
     */
    public function number(): BelongsTo
    {
        return $this->belongsTo(AgentWhatsappNumber::class, 'agent_whatsapp_number_id');
    }
}

<?php

namespace Domain\Channel\Messenger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a Messenger thread (page + page-scoped user id) to the persistent
 * Chatvia Session + Conversation behind it, so a Messenger user keeps one
 * ongoing conversation (and its history) across messages.
 *
 * @property string $psid
 * @property string $session_external_id
 * @property string $conversation_external_id
 * @property string|null $first_name
 * @property \Illuminate\Support\Carbon|null $last_message_at
 */
class MessengerThread extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(AgentMessengerPage::class, 'agent_messenger_page_id');
    }
}

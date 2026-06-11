<?php

namespace Domain\Channel\Messenger\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Maps a Messenger thread (page + page-scoped user id) to the persistent
 * Chatvia Session + Conversation behind it, so a Messenger user keeps one
 * ongoing conversation (and its history) across messages.
 *
 * @property string $psid
 * @property string $session_external_id
 * @property string $conversation_external_id
 * @property string|null $first_name
 * @property Carbon|null $last_message_at
 */
#[Guarded(['id'])]
class MessengerThread extends Model
{
    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AgentMessengerPage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(AgentMessengerPage::class, 'agent_messenger_page_id');
    }
}

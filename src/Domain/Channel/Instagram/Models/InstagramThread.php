<?php

namespace Domain\Channel\Instagram\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Maps an Instagram Direct thread (account + Instagram-scoped user id) to the
 * persistent Chatvia Session + Conversation behind it, so an Instagram user
 * keeps one ongoing conversation (and its history) across messages.
 *
 * @property string $igsid
 * @property string $session_external_id
 * @property string $conversation_external_id
 * @property string|null $name
 * @property Carbon|null $last_message_at
 */
class InstagramThread extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AgentInstagramAccount::class, 'agent_instagram_account_id');
    }
}

<?php

namespace Domain\Channel\Teams\Models;

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
 * @property string $session_external_id
 * @property string $conversation_external_id
 * @property string|null $peer_name
 * @property Carbon|null $last_message_at
 */
class TeamsChat extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(AgentTeamsBot::class, 'agent_teams_bot_id');
    }
}

<?php

namespace Domain\Channel\Teams\Models;

use Domain\Channel\Concerns\MapsChannelConversation;
use Domain\Channel\Contracts\ChannelConversationMapping;
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
 * @property int $workspace_id
 * @property int $chat_session_id
 * @property int $chat_conversation_id
 * @property string|null $peer_name
 * @property Carbon|null $last_message_at
 */
#[Guarded(['id'])]
class TeamsChat extends Model implements ChannelConversationMapping
{
    use MapsChannelConversation;

    /**
     * @return BelongsTo<AgentTeamsBot, $this>
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(AgentTeamsBot::class, 'agent_teams_bot_id');
    }

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }
}

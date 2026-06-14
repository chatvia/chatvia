<?php

namespace Domain\Channel\Slack\Models;

use Domain\Channel\Contracts\ChannelConversationMapping;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Maps a Slack conversation (app + Slack channel id — public/private channel
 * or DM) to the persistent Chatvia Session + Conversation behind it, so a
 * Slack channel keeps one ongoing conversation (and its history) across
 * messages.
 *
 * @property string $channel_id
 * @property int $chat_session_id
 * @property int $chat_conversation_id
 * @property string|null $channel_name
 * @property Carbon|null $last_message_at
 */
#[Guarded(['id'])]
class SlackChannel extends Model implements ChannelConversationMapping
{
    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AgentSlackApp, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(AgentSlackApp::class, 'agent_slack_app_id');
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

<?php

namespace Domain\Channel\Slack\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a Slack conversation (app + Slack channel id — public/private channel
 * or DM) to the persistent Chatvia Session + Conversation behind it, so a
 * Slack channel keeps one ongoing conversation (and its history) across
 * messages.
 *
 * @property string $channel_id
 * @property string $session_external_id
 * @property string $conversation_external_id
 * @property string|null $channel_name
 * @property \Illuminate\Support\Carbon|null $last_message_at
 */
class SlackChannel extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(AgentSlackApp::class, 'agent_slack_app_id');
    }
}

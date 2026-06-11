<?php

namespace Domain\Channel\Slack\Models;

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
 * @property string $session_external_id
 * @property string $conversation_external_id
 * @property string|null $channel_name
 * @property Carbon|null $last_message_at
 */
#[Guarded(['id'])]
class SlackChannel extends Model
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
}

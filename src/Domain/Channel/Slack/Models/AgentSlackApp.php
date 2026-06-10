<?php

namespace Domain\Channel\Slack\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's Slack channel: the bot token (encrypted at rest — it can post
 * messages on the workspace's behalf), the app's signing secret (encrypted —
 * verifies the HMAC signature on inbound events), and the random
 * `webhook_secret` that routes an incoming event to this agent (it's the path
 * segment of the public Events API Request URL).
 *
 * @property string $external_id
 * @property string $bot_token
 * @property string $signing_secret
 * @property string|null $bot_user_id
 * @property string|null $team_name
 * @property string $webhook_secret
 * @property bool $enabled
 */
class AgentSlackApp extends Model
{
    use HasExternalId;

    protected $guarded = ['id'];

    protected $casts = [
        'bot_token' => 'encrypted',
        'signing_secret' => 'encrypted',
        'enabled' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(SlackChannel::class);
    }
}

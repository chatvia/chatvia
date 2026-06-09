<?php

namespace Domain\Channel\Telegram\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's Telegram channel: the bot token (encrypted at rest — it can send
 * messages on the workspace's behalf) plus the random `webhook_secret` that both
 * routes an incoming update to this agent (it's in the public webhook URL) and
 * verifies it (Telegram echoes it in the `X-Telegram-Bot-Api-Secret-Token` header).
 *
 * @property string $external_id
 * @property string $bot_token
 * @property string|null $bot_username
 * @property string $webhook_secret
 * @property bool $enabled
 */
class AgentTelegramBot extends Model
{
    use HasExternalId;

    protected $guarded = ['id'];

    protected $casts = [
        'bot_token' => 'encrypted',
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

    public function chats(): HasMany
    {
        return $this->hasMany(TelegramChat::class);
    }
}

<?php

namespace Domain\Channel\Telegram\Models;

use Database\Factories\AgentTelegramBotFactory;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's Telegram channel: the bot token (encrypted at rest — it can send
 * messages on the workspace's behalf), the random `webhook_secret` that routes
 * an incoming update to this agent (it is in the public webhook URL), and a
 * second random `header_secret` (encrypted) that verifies it: Telegram echoes
 * it in the `X-Telegram-Bot-Api-Secret-Token` header. Two secrets, so a
 * webhook URL seen in a log or a proxy is not enough to forge an update.
 *
 * @property string $external_id
 * @property string $bot_token
 * @property string|null $bot_username
 * @property string $webhook_secret
 * @property string $header_secret
 * @property bool $is_enabled
 * @property list<array{command: string, description: string, type: string, value: string}>|null $commands
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class AgentTelegramBot extends Model
{
    /** @use HasFactory<AgentTelegramBotFactory> */
    use HasExternalId, HasFactory;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return HasMany<TelegramChat, $this> */
    public function chats(): HasMany
    {
        return $this->hasMany(TelegramChat::class);
    }

    protected function casts(): array
    {
        return [
            'bot_token' => 'encrypted',
            'header_secret' => 'encrypted',
            'is_enabled' => 'boolean',
            'commands' => 'array',
        ];
    }
}

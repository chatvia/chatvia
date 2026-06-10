<?php

namespace Domain\Channel\Teams\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's Microsoft Teams channel: the Azure bot's Microsoft App ID (the
 * audience of the JWT that authenticates inbound activities — not a secret),
 * its client secret (encrypted at rest — it mints the Bot Framework tokens
 * replies are sent with), the Entra tenant id for single-tenant bots (null =
 * multi-tenant), and the random `webhook_secret` that routes an incoming
 * activity to this agent (it's the path segment of the public messaging
 * endpoint URL).
 *
 * @property string $external_id
 * @property string $app_id
 * @property string $app_secret
 * @property string|null $tenant_id
 * @property string $webhook_secret
 * @property bool $enabled
 */
class AgentTeamsBot extends Model
{
    use HasExternalId;

    protected $guarded = ['id'];

    protected $casts = [
        'app_secret' => 'encrypted',
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
        return $this->hasMany(TeamsChat::class);
    }
}

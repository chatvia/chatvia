<?php

namespace Domain\Channel\Messenger\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's Messenger channel: the Facebook Page access token (encrypted at
 * rest — it can send messages as the page), the Meta app secret (encrypted —
 * verifies the HMAC signature on inbound webhooks), and the random
 * `webhook_secret` that routes an incoming event to this agent (it's the path
 * segment of the public Callback URL and the verify token of Meta's GET
 * webhook handshake).
 *
 * @property string $external_id
 * @property string $page_access_token
 * @property string $app_secret
 * @property string|null $page_id
 * @property string|null $page_name
 * @property string $webhook_secret
 * @property bool $enabled
 */
class AgentMessengerPage extends Model
{
    use HasExternalId;

    protected $guarded = ['id'];

    protected $casts = [
        'page_access_token' => 'encrypted',
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

    public function threads(): HasMany
    {
        return $this->hasMany(MessengerThread::class);
    }
}

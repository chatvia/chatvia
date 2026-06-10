<?php

namespace Domain\Channel\Instagram\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's Instagram Direct channel: the professional account's access
 * token (encrypted at rest — it can send DMs as the account), the Instagram
 * app secret (encrypted — verifies the HMAC signature on inbound webhooks),
 * and the random `webhook_secret` that routes an incoming event to this agent
 * (it's the path segment of the public Callback URL and the verify token of
 * Meta's GET webhook handshake). Connected via the Instagram API with
 * Instagram Login — no Facebook Page involved.
 *
 * @property string $external_id
 * @property string $access_token
 * @property string $app_secret
 * @property string|null $ig_id
 * @property string|null $username
 * @property string $webhook_secret
 * @property bool $enabled
 */
class AgentInstagramAccount extends Model
{
    use HasExternalId;

    protected $guarded = ['id'];

    protected $casts = [
        'access_token' => 'encrypted',
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
        return $this->hasMany(InstagramThread::class);
    }
}

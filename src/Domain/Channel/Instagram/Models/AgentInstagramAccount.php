<?php

namespace Domain\Channel\Instagram\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's Instagram Direct channel, connected via Facebook Login for Business
 * (one Chatvia Meta app). The professional account is linked to a Facebook Page;
 * we store that Page's access token (encrypted — it sends DMs as the account)
 * and the IG account id (`ig_id`, the routing key for inbound webhooks). Inbound
 * events arrive on Chatvia's single app-level Instagram Callback URL and are
 * routed here by `ig_id`; the HMAC + verify token are app-level config.
 *
 * The legacy per-account `access_token`/`app_secret`/`webhook_secret` columns are
 * nullable and unused by the OAuth flow (kept for already-connected rows).
 *
 * @property string $external_id
 * @property string|null $page_access_token
 * @property string|null $page_id
 * @property string|null $access_token
 * @property string|null $app_secret
 * @property string|null $ig_id
 * @property string|null $username
 * @property string|null $webhook_secret
 * @property bool $enabled
 */
#[Guarded(['id'])]
class AgentInstagramAccount extends Model
{
    use HasExternalId;

    protected $casts = [
        'page_access_token' => 'encrypted',
        // `access_token` / `app_secret` are legacy, never-read, now-nullable
        // columns — deliberately NOT cast: the `encrypted` caster can't decrypt a
        // null and would throw on every OAuth-era row (which leaves them null).
        'enabled' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(InstagramThread::class);
    }
}

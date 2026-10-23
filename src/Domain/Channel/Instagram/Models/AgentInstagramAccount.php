<?php

namespace Domain\Channel\Instagram\Models;

use Database\Factories\AgentInstagramAccountFactory;
use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\MetaApp;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's Instagram Direct channel, connected via Facebook Login for Business
 * (the Instagram Meta app set in the admin panel, {@see MetaApp::Instagram}).
 * The professional account is linked to a Facebook Page;
 * we store that Page's access token (encrypted — it sends DMs as the account)
 * and the IG account id (`ig_id`, the routing key for inbound webhooks). Inbound
 * events arrive on Chatvia's single app-level Instagram Callback URL and are
 * routed here by `ig_id`; the HMAC + verify token are app-level config.
 *
 * @property string $external_id
 * @property string|null $page_access_token
 * @property string|null $page_id
 * @property string|null $ig_id
 * @property string|null $username
 * @property bool $is_enabled
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class AgentInstagramAccount extends Model
{
    /** @use HasFactory<AgentInstagramAccountFactory> */
    use HasExternalId, HasFactory;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return HasMany<InstagramThread, $this> */
    public function threads(): HasMany
    {
        return $this->hasMany(InstagramThread::class);
    }

    protected function casts(): array
    {
        return [
            'page_access_token' => 'encrypted',
            'is_enabled' => 'boolean',
        ];
    }
}

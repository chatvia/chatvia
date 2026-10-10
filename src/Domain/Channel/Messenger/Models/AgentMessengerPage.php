<?php

namespace Domain\Channel\Messenger\Models;

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\MetaApp;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's Messenger channel, connected through Facebook Login for Business:
 * the Facebook Page access token (encrypted at rest — it can send messages as
 * the page) and the page id, which routes inbound events from the app-level
 * webhook to this agent. The webhook signature and verify token come from the
 * Messenger Meta app set in the admin panel ({@see MetaApp::Messenger}).
 *
 * @property string $external_id
 * @property string $page_access_token
 * @property string|null $page_id
 * @property string|null $page_name
 * @property bool $enabled
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class AgentMessengerPage extends Model
{
    use HasExternalId;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return HasMany<MessengerThread, $this> */
    public function threads(): HasMany
    {
        return $this->hasMany(MessengerThread::class);
    }

    protected function casts(): array
    {
        return [
            'page_access_token' => 'encrypted',
            'enabled' => 'boolean',
        ];
    }
}

<?php

namespace Domain\Channel\Whatsapp\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's WhatsApp channel: the Cloud API access token (encrypted at rest —
 * it can send messages as the business number), the Meta app secret
 * (encrypted — verifies the HMAC signature on inbound webhooks), and the
 * random `webhook_secret` that routes an incoming event to this agent (it's
 * the path segment of the public Callback URL and the verify token of Meta's
 * GET webhook handshake). `phone_number_id` is both the send endpoint and the
 * payload-level routing key when several numbers share one Meta app.
 *
 * @property string $external_id
 * @property string $access_token
 * @property string $app_secret
 * @property string $phone_number_id
 * @property string $waba_id
 * @property string|null $display_phone_number
 * @property string|null $verified_name
 * @property string $webhook_secret
 * @property bool $enabled
 */
class AgentWhatsappNumber extends Model
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

    public function chats(): HasMany
    {
        return $this->hasMany(WhatsappChat::class);
    }
}

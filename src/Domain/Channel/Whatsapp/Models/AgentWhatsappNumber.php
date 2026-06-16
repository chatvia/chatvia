<?php

namespace Domain\Channel\Whatsapp\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's WhatsApp channel: the Cloud API access token (encrypted at rest —
 * a business-integration token from WhatsApp Embedded Signup that can send
 * messages as the number). `phone_number_id` is both the send endpoint and the
 * routing key — inbound events land on the single app-level webhook and are
 * matched to this row by it. `app_secret` / `webhook_secret` are legacy columns
 * from the old BYO paste-token model (HMAC is now verified with the shared
 * Chatvia app secret and the webhook is app-level); they stay nullable for
 * historical rows and are no longer written.
 *
 * @property string $external_id
 * @property string $access_token
 * @property string|null $app_secret
 * @property string $phone_number_id
 * @property string $waba_id
 * @property string|null $display_phone_number
 * @property string|null $verified_name
 * @property string|null $webhook_secret
 * @property bool $enabled
 */
#[Guarded(['id'])]
class AgentWhatsappNumber extends Model
{
    use HasExternalId;

    protected $casts = [
        'access_token' => 'encrypted',
        // `app_secret` is a legacy, never-read, now-nullable column — deliberately
        // NOT cast: the `encrypted` caster can't decrypt a null and would throw on
        // every OAuth-era row (which leaves it null). A raw null/ciphertext read is
        // harmless since nothing consumes it.
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

    public function chats(): HasMany
    {
        return $this->hasMany(WhatsappChat::class);
    }
}

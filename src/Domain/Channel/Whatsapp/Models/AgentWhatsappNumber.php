<?php

namespace Domain\Channel\Whatsapp\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Support\Concerns\HasExternalId;

/**
 * An agent's WhatsApp channel: the Cloud API access token (encrypted at rest —
 * a business-integration token from WhatsApp Embedded Signup that can send
 * messages as the number). `phone_number_id` is both the send endpoint and the
 * routing key — inbound events land on the single app-level webhook and are
 * matched to this row by it. The `app_secret` / `webhook_secret` columns are
 * not used: the HMAC is verified with the shared Chatvia app secret and the
 * webhook is app-level. They are nullable, never written, and only hold data
 * on older rows.
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
#[RouteKey('external_id')]
class AgentWhatsappNumber extends Model
{
    use HasExternalId;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return HasMany<WhatsappChat, $this> */
    public function chats(): HasMany
    {
        return $this->hasMany(WhatsappChat::class);
    }

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            // `app_secret` is never read and is null on rows connected through
            // OAuth, so it is deliberately NOT cast: the `encrypted` caster can't
            // decrypt a null and would throw. Reading the raw value is harmless
            // because nothing uses it.
            'enabled' => 'boolean',
        ];
    }
}

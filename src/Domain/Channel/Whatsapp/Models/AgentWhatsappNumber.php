<?php

namespace Domain\Channel\Whatsapp\Models;

use Database\Factories\AgentWhatsappNumberFactory;
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
 * An agent's WhatsApp channel: the Cloud API access token (encrypted at rest —
 * a business-integration token from WhatsApp Embedded Signup that can send
 * messages as the number). `phone_number_id` is both the send endpoint and the
 * routing key — inbound events land on the single app-level webhook and are
 * matched to this row by it. The webhook signature and verify token come from
 * the WhatsApp Meta app set in the admin panel ({@see MetaApp::Whatsapp}).
 *
 * @property string $external_id
 * @property string $access_token
 * @property string $phone_number_id
 * @property string $waba_id
 * @property string|null $display_phone_number
 * @property string|null $verified_name
 * @property bool $is_enabled
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class AgentWhatsappNumber extends Model
{
    /** @use HasFactory<AgentWhatsappNumberFactory> */
    use HasExternalId, HasFactory;

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
            'is_enabled' => 'boolean',
        ];
    }
}

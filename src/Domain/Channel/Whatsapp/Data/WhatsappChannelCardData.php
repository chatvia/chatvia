<?php

namespace Domain\Channel\Whatsapp\Data;

use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The WhatsApp card of the agent's Channels page. WhatsApp connects via
 * Embedded Signup (the Facebook JS SDK popup), so there's no per-number
 * webhook URL — the frontend just needs the WhatsApp Meta app's id + Login
 * configuration to launch it. `available` is false until an admin sets up
 * that app.
 */
#[TypeScript]
class WhatsappChannelCardData extends Data
{
    public function __construct(
        public bool $available,
        public bool $connected,
        public ?string $display_phone_number,
        public ?string $verified_name,
        public bool $enabled,
        public ?string $app_id,
        public ?string $config_id,
        public ?string $graph_version,
    ) {}

    public static function fromNumber(?AgentWhatsappNumber $number): self
    {
        return new self(
            available: MetaApp::Whatsapp->isConfigured(),
            connected: $number instanceof AgentWhatsappNumber,
            display_phone_number: $number?->display_phone_number,
            verified_name: $number?->verified_name,
            enabled: (bool) $number?->is_enabled,
            app_id: MetaApp::Whatsapp->appId() ?: null,
            config_id: MetaApp::Whatsapp->configId(),
            graph_version: config('services.meta.graph_version'),
        );
    }
}

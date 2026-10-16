<?php

namespace Domain\Channel\Teams\Data;

use Domain\Channel\Teams\Models\AgentTeamsBot;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Microsoft Teams card of the agent's Channels page. The app id doubles
 * as the "Open in Teams" deep-link target (a bot's Teams user id is
 * `28:{app_id}`).
 */
#[TypeScript]
class TeamsChannelCardData extends Data
{
    public function __construct(
        public bool $connected,
        public ?string $app_id,
        public ?string $webhook_url,
        public bool $enabled,
    ) {}

    public static function fromBot(?AgentTeamsBot $bot): self
    {
        return new self(
            connected: $bot !== null,
            app_id: $bot?->app_id,
            webhook_url: $bot !== null ? route('msteams.webhook', ['secret' => $bot->webhook_secret]) : null,
            enabled: (bool) $bot?->is_enabled,
        );
    }
}

<?php

namespace Domain\Channel\Slack\Data;

use Domain\Channel\Slack\Models\AgentSlackApp;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Slack card of the agent's Channels page. The webhook URL embeds the
 * per-app secret — the workspace pastes it into their Slack app's Event
 * Subscriptions after connecting.
 */
#[TypeScript]
class SlackChannelCardData extends Data
{
    public function __construct(
        public bool $connected,
        public ?string $team_name,
        public ?string $webhook_url,
        public bool $enabled,
    ) {}

    public static function fromApp(?AgentSlackApp $app): self
    {
        return new self(
            connected: $app !== null,
            team_name: $app?->team_name,
            webhook_url: $app !== null ? route('slack.webhook', ['secret' => $app->webhook_secret]) : null,
            enabled: (bool) $app?->is_enabled,
        );
    }
}

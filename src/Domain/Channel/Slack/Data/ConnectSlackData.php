<?php

declare(strict_types=1);

namespace Domain\Channel\Slack\Data;

use Spatie\LaravelData\Data;

/**
 * The credentials a workspace pastes from its Slack app: the bot token (sends
 * and edits messages) and the signing secret (verifies inbound events).
 */
class ConnectSlackData extends Data
{
    public function __construct(
        public string $botToken,
        public string $signingSecret,
    ) {}
}

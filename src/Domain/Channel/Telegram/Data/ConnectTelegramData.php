<?php

namespace Domain\Channel\Telegram\Data;

use Spatie\LaravelData\Data;

/**
 * The bot token (from @BotFather) a workspace pastes to connect a Telegram bot.
 */
class ConnectTelegramData extends Data
{
    public function __construct(
        public string $botToken,
    ) {}
}

<?php

namespace Domain\Channel\Telegram\Actions;

use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramClient;

/**
 * Disconnects an agent's Telegram bot: asks Telegram to stop delivering
 * updates to our webhook (best-effort — the bot may already be gone), then
 * deletes the connection and its chat mappings.
 */
class DisconnectTelegramBotAction
{
    public function __construct(
        private readonly TelegramClient $client,
    ) {}

    public function execute(AgentTelegramBot $bot): void
    {
        $this->client->deleteWebhook($bot->bot_token);
        $bot->delete();
    }
}

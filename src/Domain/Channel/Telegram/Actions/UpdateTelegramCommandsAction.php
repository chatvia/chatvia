<?php

declare(strict_types=1);

namespace Domain\Channel\Telegram\Actions;

use Domain\Channel\Telegram\Data\UpdateTelegramCommandsData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;

/**
 * Replaces an agent's Telegram commands (built-in overrides + custom commands) and
 * re-registers the bot's "/" menu with Telegram. Built-in defaults are merged back
 * in at read time by {@see TelegramCommandRouter}, so an empty list is safe.
 */
class UpdateTelegramCommandsAction
{
    public function __construct(
        private readonly TelegramCommandRouter $commands,
    ) {}

    public function execute(AgentTelegramBot $bot, UpdateTelegramCommandsData $data): void
    {
        $bot->update(['commands' => $data->toStoredCommands()]);

        $this->commands->sync($bot);
    }
}

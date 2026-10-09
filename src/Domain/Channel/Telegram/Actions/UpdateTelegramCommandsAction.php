<?php

declare(strict_types=1);

namespace Domain\Channel\Telegram\Actions;

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

    /**
     * @param  list<array{command: string, description: string, type: string, value: string}>  $commands
     */
    public function execute(AgentTelegramBot $bot, array $commands): void
    {
        $bot->update(['commands' => array_values($commands)]);

        $this->commands->sync($bot);
    }
}

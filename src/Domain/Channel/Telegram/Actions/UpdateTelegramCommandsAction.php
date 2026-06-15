<?php

namespace Domain\Channel\Telegram\Actions;

use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;

/**
 * Replaces an agent's custom Telegram commands and re-registers the bot's command
 * menu with Telegram. Built-in commands (/start, /reset, /help) are not stored —
 * they're always available and prepended to the menu by {@see TelegramCommandRouter::menu()}.
 */
class UpdateTelegramCommandsAction
{
    public function __construct(
        private readonly TelegramCommandRouter $commands,
    ) {}

    /**
     * @param  list<array{command: string, description: string, reply: string}>  $commands
     */
    public function execute(AgentTelegramBot $bot, array $commands): void
    {
        $bot->update(['custom_commands' => array_values($commands)]);

        $this->commands->sync($bot);
    }
}

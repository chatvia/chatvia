<?php

declare(strict_types=1);

namespace Domain\Channel\Telegram\Data;

use Spatie\LaravelData\Data;

/**
 * The full command list of an agent's Telegram bot as the owner saves it:
 * built-in overrides (/start, /reset, /help) plus custom commands.
 */
class UpdateTelegramCommandsData extends Data
{
    /**
     * @param  list<TelegramCommandData>  $commands
     */
    public function __construct(
        public array $commands,
    ) {}

    /**
     * The commands as the bot's `commands` column stores them.
     *
     * @return list<array{command: string, description: string, type: string, value: string}>
     */
    public function toStoredCommands(): array
    {
        /** @var list<array{command: string, description: string, type: string, value: string}> */
        return array_map(static fn (TelegramCommandData $command): array => $command->toArray(), $this->commands);
    }
}

<?php

namespace Domain\Channel\Telegram\Data;

use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Telegram card of the agent's Channels page.
 */
#[TypeScript]
class TelegramChannelCardData extends Data
{
    public function __construct(
        public bool $connected,
        public ?string $bot_username,
        public bool $enabled,
        /** @var DataCollection<int, TelegramCommandRowData> */
        #[DataCollectionOf(TelegramCommandRowData::class)]
        public DataCollection $commands,
    ) {}

    /**
     * @param  list<array{command: string, description: string, type: string, value: string, builtin: bool}>  $commands
     */
    public static function fromBot(?AgentTelegramBot $bot, array $commands): self
    {
        return new self(
            connected: $bot !== null,
            bot_username: $bot?->bot_username,
            enabled: (bool) $bot?->enabled,
            commands: new DataCollection(TelegramCommandRowData::class, array_map(TelegramCommandRowData::fromArray(...), $commands)),
        );
    }
}

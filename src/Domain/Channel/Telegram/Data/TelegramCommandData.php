<?php

namespace Domain\Channel\Telegram\Data;

use Spatie\LaravelData\Data;

/**
 * One Telegram command as the owner saves it: `type` is "message" (canned
 * text sent as-is) or "prompt" (run through the agent). This shape is stored
 * verbatim on the bot's `commands` column.
 */
class TelegramCommandData extends Data
{
    public function __construct(
        public string $command,
        public string $description,
        public string $type,
        public string $value,
    ) {}
}

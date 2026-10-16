<?php

namespace Domain\Channel\Telegram\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One Telegram command in the Channels page editor: a built-in default
 * (/start, /reset, /help — overridable, not removable) or a custom command.
 * `type` is "message" (canned text sent as-is) or "prompt" (run through the
 * agent).
 */
#[TypeScript]
class TelegramCommandRowData extends Data
{
    public function __construct(
        public string $command,
        public string $description,
        public string $type,
        public string $value,
        public bool $builtin,
    ) {}

    /**
     * @param  array{command: string, description: string, type: string, value: string, builtin: bool}  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            command: $row['command'],
            description: $row['description'],
            type: $row['type'],
            value: $row['value'],
            builtin: $row['builtin'],
        );
    }
}

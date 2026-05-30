<?php

namespace Domain\Widget\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WidgetEndChatResultData extends Data
{
    public function __construct(
        public bool $success,
        public string $message,
    ) {}

    public static function ended(): self
    {
        return new self(true, 'Live chat ended');
    }

    public static function notInHandoff(): self
    {
        return new self(true, 'Conversation not in handoff mode');
    }
}

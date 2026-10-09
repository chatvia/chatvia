<?php

namespace Domain\Widget\Data\Session;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Answer to a visitor ending a live chat; also successful when no live chat
 * was running, so the widget can always return to the AI.
 */
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

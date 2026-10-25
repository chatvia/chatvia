<?php

declare(strict_types=1);

namespace Domain\Agent\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a visitor's "remove this chat" does in the Chat Widget (Setup →
 * Behavior → Widget): nothing is removed (the visitor can only end the chat
 * and start a new one), the chat leaves the visitor's history but stays in the
 * chat log, or it is erased. The value is stored in the agent's settings and
 * sent to the widget, so never rename one.
 */
#[TypeScript]
enum ChatDeletionMode: string
{
    case End = 'end';
    case Hide = 'hide';
    case Delete = 'delete';
}

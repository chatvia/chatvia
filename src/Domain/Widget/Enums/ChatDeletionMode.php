<?php

declare(strict_types=1);

namespace Domain\Widget\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a visitor's "remove this chat" does in the Chat Widget, for every agent
 * of the install (admin panel → Settings → Widget): nothing is removed (the
 * visitor can only end the chat and start a new one), the chat leaves the
 * visitor's history but stays in the chat log, or it is erased. The value is
 * stored in the settings and sent to the widget, so never rename one.
 */
#[TypeScript]
enum ChatDeletionMode: string
{
    case End = 'end';
    case Hide = 'hide';
    case Delete = 'delete';

    /** The admin's choice; ending the chat until one is saved. */
    public static function inUse(): self
    {
        return self::tryFrom((string) config('chatvia.widget.chat_deletion_mode')) ?? self::End;
    }
}

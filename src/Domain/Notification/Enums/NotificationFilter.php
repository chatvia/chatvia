<?php

declare(strict_types=1);

namespace Domain\Notification\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which notifications a list shows: all of them, or only the unread ones.
 */
#[TypeScript]
enum NotificationFilter: string
{
    case All = 'all';
    case Unread = 'unread';
}

<?php

declare(strict_types=1);

namespace Domain\Notification\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The groups a person switches notifications on and off by, on their account's
 * Notifications page: whether a category rings the bell, shows a desktop alert
 * or plays a sound. Stored in `user_notifications.category` and in each
 * user's preferences, so the values must not change.
 */
#[TypeScript]
enum NotificationCategory: string
{
    case Handoffs = 'handoffs';
    case Security = 'security';
    case Training = 'training';
    case Leads = 'leads';
    case Usage = 'usage';
    case Reports = 'reports';

    /** Desktop alerts are on by default only where someone should act now. */
    public function desktopByDefault(): bool
    {
        return collect([self::Handoffs, self::Security])->containsStrict($this);
    }

    /** Only a waiting visitor makes a sound by default. */
    public function soundByDefault(): bool
    {
        return $this === self::Handoffs;
    }
}

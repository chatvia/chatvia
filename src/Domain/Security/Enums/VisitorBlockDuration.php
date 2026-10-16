<?php

declare(strict_types=1);

namespace Domain\Security\Enums;

use Illuminate\Support\Carbon;

/**
 * How long a visitor block lasts. Network addresses change hands, so a block
 * normally ends; "forever" lasts until someone removes it.
 */
enum VisitorBlockDuration: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Forever = 'forever';

    /** When a block made now ends, or null when it never does. */
    public function expiresAt(): ?Carbon
    {
        return match ($this) {
            self::Day => now()->addDay(),
            self::Week => now()->addWeek(),
            self::Month => now()->addDays(30),
            self::Forever => null,
        };
    }
}

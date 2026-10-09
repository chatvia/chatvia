<?php

declare(strict_types=1);

namespace Domain\Analytics\Enums;

/**
 * The visitor's tone, as the analysis model classifies it. Stored on messages
 * and conversations and in the daily tone rollup. The values (and the class
 * name, which an early migration references) must not change.
 */
enum ToneEnum: string
{
    case NEUTRAL = 'neutral';

    case FRUSTRATED = 'frustrated';

    case URGENT = 'urgent';

    case CONFUSED = 'confused';

    case SATISFIED = 'satisfied';

    /**
     * Get all tone values as an array for database constraints.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

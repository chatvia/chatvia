<?php

namespace App\Enums;

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

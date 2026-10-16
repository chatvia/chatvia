<?php

declare(strict_types=1);

namespace Domain\Analytics\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The visitor's tone, as the analysis model classifies it. Stored on messages
 * and conversations and in the daily tone rollup; the values must not change.
 */
#[TypeScript]
enum Tone: string
{
    case Neutral = 'neutral';

    case Frustrated = 'frustrated';

    case Urgent = 'urgent';

    case Confused = 'confused';

    case Satisfied = 'satisfied';

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

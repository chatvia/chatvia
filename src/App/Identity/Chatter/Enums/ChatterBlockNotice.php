<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Enums;

/**
 * Whether a blocked chatter is told by email that their access was turned off.
 */
enum ChatterBlockNotice: string
{
    case Email = 'email';
    case Silent = 'silent';
}

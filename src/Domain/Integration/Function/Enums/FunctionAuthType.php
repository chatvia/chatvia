<?php

declare(strict_types=1);

namespace Domain\Integration\Function\Enums;

/**
 * How Chatvia authenticates a function's outbound webhook call.
 */
enum FunctionAuthType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Header = 'header';
    case Hmac = 'hmac';

    /** Whether outbound calls of this auth type carry a configured credential. */
    public function requiresCredential(): bool
    {
        return match ($this) {
            self::None => false,
            self::Bearer, self::Header, self::Hmac => true,
        };
    }
}

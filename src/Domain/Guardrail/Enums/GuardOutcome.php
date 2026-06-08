<?php

namespace Domain\Guardrail\Enums;

/**
 * What the guard did when it triggered. Only triggers are logged — a clean pass
 * produces no event.
 *
 *  - Blocked:  the turn was stopped (input refused / answer suppressed).
 *  - Redacted: the offending span was masked but the turn continued.
 *  - Flagged:  allowed through, recorded for review only.
 */
enum GuardOutcome: string
{
    case Blocked = 'blocked';
    case Redacted = 'redacted';
    case Flagged = 'flagged';

    public function label(): string
    {
        return match ($this) {
            self::Blocked => 'Blocked',
            self::Redacted => 'Redacted',
            self::Flagged => 'Flagged',
        };
    }
}

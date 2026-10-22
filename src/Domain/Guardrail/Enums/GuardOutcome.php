<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the guard did when it triggered. Only triggers are logged — a clean pass
 * produces no event.
 *
 *  - Blocked:  the turn was stopped (input refused / answer suppressed).
 *  - Redacted: the offending span was masked but the turn continued.
 *  - Flagged:  allowed through, recorded for review only.
 *  - Skipped:  the guard could not run (the safety model failed), so the text
 *              was let through unchecked. Logged so the owner can see it.
 */
#[TypeScript]
enum GuardOutcome: string
{
    case Blocked = 'blocked';
    case Redacted = 'redacted';
    case Flagged = 'flagged';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Blocked => 'Blocked',
            self::Redacted => 'Redacted',
            self::Flagged => 'Flagged',
            self::Skipped => 'Skipped',
        };
    }
}

<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

/**
 * What a guard does when it fires: block the message or answer, or only log
 * it (monitor). Monitor lets an owner see what a guard would catch, and how
 * often it is wrong, before it affects visitors.
 */
enum GuardMode: string
{
    case Block = 'block';
    case Monitor = 'monitor';

    /** The outcome of a finding that is serious enough to block. */
    public function outcome(): GuardOutcome
    {
        return $this === self::Block ? GuardOutcome::Blocked : GuardOutcome::Flagged;
    }
}

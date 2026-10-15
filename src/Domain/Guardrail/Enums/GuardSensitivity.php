<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

/**
 * How easily a guard blocks, set per guard. The classifier rates each finding
 * low, medium or high; a high sensitivity blocks from low up, medium from
 * medium up, and low only blocks high findings. A finding below the line is
 * flagged (logged) and let through.
 */
enum GuardSensitivity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function blocks(GuardSeverity $severity): bool
    {
        return match ($this) {
            self::High => $severity !== GuardSeverity::None,
            self::Medium => $severity->blocks(),
            self::Low => $severity === GuardSeverity::High,
        };
    }
}

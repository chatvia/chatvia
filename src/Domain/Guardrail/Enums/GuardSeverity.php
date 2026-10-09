<?php

namespace Domain\Guardrail\Enums;

/**
 * How serious a classifier finding is (prompt-injection risk, toxicity
 * severity). Stored in `guardrail_events.severity`. Medium and High block a
 * turn; Low is only flagged.
 */
enum GuardSeverity: string
{
    case None = 'none';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /** A model answer outside the four levels counts as None. */
    public static function fromModel(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom(strtolower(trim($value))) ?? self::None) : self::None;
    }

    public function blocks(): bool
    {
        return $this === self::Medium || $this === self::High;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace Domain\Guardrail\Enums;

use Illuminate\Support\Str;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How serious a classifier finding is (prompt-injection risk, toxicity
 * severity). Stored in `guardrail_events.severity`. Medium and High block a
 * turn; Low is only flagged.
 */
#[TypeScript]
enum GuardSeverity: string
{
    case None = 'none';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /** A model answer outside the four levels counts as None. */
    public static function fromModel(mixed $value): self
    {
        return filled($value) ? (self::tryFrom(Str::lower(Str::trim($value))) ?? self::None) : self::None;
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

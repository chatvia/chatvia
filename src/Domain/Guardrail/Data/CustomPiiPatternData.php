<?php

declare(strict_types=1);

namespace Domain\Guardrail\Data;

use Domain\Guardrail\Enums\PiiHandling;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * An owner-defined kind of personal data (a customer number, a membership
 * ID), found by a regular expression and masked as `[NAME_n]` or blocked
 * like the built-in filters. The pattern is a plain expression without
 * delimiters; it is checked when saved.
 */
class CustomPiiPatternData extends Data
{
    public function __construct(
        public string $name,
        public string $pattern,
        public PiiHandling $action = PiiHandling::Mask,
    ) {}

    public static function fromStored(mixed $stored): ?self
    {
        if (! is_array($stored) || blank($stored['name'] ?? null) || blank($stored['pattern'] ?? null)) {
            return null;
        }

        return new self(
            name: trim((string) $stored['name']),
            pattern: (string) $stored['pattern'],
            action: PiiHandling::tryFrom((string) ($stored['action'] ?? '')) ?? PiiHandling::Mask,
        );
    }

    /** The key it is logged and configured under, e.g. `custom_customer_number`. */
    public function type(): string
    {
        return 'custom_'.Str::snake(Str::ascii($this->name));
    }

    /** The placeholder label, e.g. `CUSTOMER_NUMBER` → [CUSTOMER_NUMBER_1]. */
    public function label(): string
    {
        return strtoupper(Str::snake(Str::ascii($this->name)));
    }

    /** The pattern as a PHP regular expression (case-insensitive, Unicode). */
    public function regex(): string
    {
        return '~'.str_replace('~', '\~', $this->pattern).'~iu';
    }

    /** Whether the pattern compiles. */
    public static function isValidPattern(string $pattern): bool
    {
        return @preg_match('~'.str_replace('~', '\~', $pattern).'~iu', '') !== false;
    }

    /**
     * @return array<string, string>
     */
    public function toStored(): array
    {
        return ['name' => $this->name, 'pattern' => $this->pattern, 'action' => $this->action->value];
    }
}

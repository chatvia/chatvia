<?php

namespace Domain\Guardrail\Services\Detectors;

use Domain\Guardrail\Data\PiiRedactionData;
use Illuminate\Support\Str;

/**
 * Mutable accumulator threaded through the PII filter pipeline. Each filter masks
 * its own entity type in {@see $text}; the state owns the shared masking mechanics
 * (regex replace + typed, numbered placeholder assignment), skips the values the
 * {@see PiiAllowlist} allows, and tallies which types fired and how many spans
 * were masked.
 */
final class PiiRedactionState
{
    /** @var array<string, array<string, string>> type => (raw value => placeholder) */
    private array $assigned = [];

    /** @var list<string> */
    private array $types = [];

    private int $count = 0;

    public function __construct(
        public string $text,
        private readonly PiiAllowlist $allowlist = new PiiAllowlist,
    ) {}

    /**
     * Replace every accepted match of $pattern with a typed, numbered placeholder
     * ([EMAIL_1], …). Repeated values reuse their token (coreference); distinct
     * values get the next number. $accept gates a candidate before masking
     * (e.g. a Luhn check for cards) — a rejected or allowed match is left
     * untouched.
     */
    public function mask(string $pattern, string $label, string $type, ?callable $accept = null): void
    {
        $hits = 0;
        $this->assigned[$type] ??= [];

        $masked = Str::replaceMatches($pattern, function (array $m) use ($label, $type, $accept, &$hits): string {
            $value = $m[0];
            if ($this->allowlist->allows($value) || ($accept !== null && ! $accept($value))) {
                return $value;
            }
            $hits++;

            return $this->assigned[$type][$value] ??= '['.$label.'_'.(count($this->assigned[$type]) + 1).']';
        }, $this->text);

        // A pattern that fails (an owner's pattern that backtracks too much)
        // leaves the text as it was rather than wiping it.
        if ($masked === null) {
            return;
        }

        $this->text = $masked;

        if ($hits > 0) {
            $this->types[] = $type;
            $this->count += $hits;
        }
    }

    public function result(): PiiRedactionData
    {
        return new PiiRedactionData($this->text, array_values(array_unique($this->types)), $this->count);
    }
}

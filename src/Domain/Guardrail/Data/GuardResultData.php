<?php

namespace Domain\Guardrail\Data;

/**
 * The outcome of running every enabled guard over one piece of text (a user
 * message, a source chunk, or an answer): the possibly-redacted `text`, whether
 * the turn must be blocked, the refusal to show if so, and the list of guards
 * that fired (for the audit log).
 */
final readonly class GuardResultData
{
    /**
     * @param  list<GuardVerdictData>  $verdicts
     */
    public function __construct(
        public string $text,
        public bool $blocked = false,
        public ?string $refusalMessage = null,
        public array $verdicts = [],
    ) {}

    public static function clean(string $text): self
    {
        return new self($text);
    }

    public function triggered(): bool
    {
        return $this->verdicts !== [];
    }
}

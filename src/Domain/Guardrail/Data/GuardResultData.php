<?php

namespace Domain\Guardrail\Data;

/**
 * The outcome of running every enabled guard over one piece of text (a user
 * message, a source chunk, or an answer): the possibly-redacted `text`, whether
 * the turn must be blocked, the refusal to show if so (in the text's language),
 * the list of guards that fired (for the audit log), and the language the
 * classifier saw the text in (ISO 639-1, null when no classifier ran).
 *
 * `handoffMessage` is set when the block came from a topic that hands the
 * conversation to a person: it is shown instead of the refusal when the
 * handoff can start (Chat Widget with live chat on), else the refusal is.
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
        public ?string $language = null,
        public ?string $handoffMessage = null,
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

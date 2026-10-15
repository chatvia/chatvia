<?php

namespace Domain\Guardrail\Data;

use Domain\Guardrail\Enums\GuardSeverity;

/**
 * The parsed classifier result, plus the language of the text (ISO 639-1, so
 * a refusal can be shown in it) and the 1-based number of the blocked topic
 * the text is about (0 for none). `failed` is true when the classifier errored
 * or returned garbage — guards FAIL OPEN (treat as no-trigger) so a model
 * outage degrades safety rather than taking the whole chat down.
 */
final readonly class ClassifierVerdictData
{
    /**
     * @param  list<string>  $toxicityCategories
     */
    public function __construct(
        public bool $injection = false,
        public GuardSeverity $injectionRisk = GuardSeverity::None,
        public bool $offTopic = false,
        public bool $toxic = false,
        public GuardSeverity $toxicitySeverity = GuardSeverity::None,
        public array $toxicityCategories = [],
        public ?string $reason = null,
        public bool $failed = false,
        public ?string $language = null,
        public int $matchedTopic = 0,
    ) {}

    public static function failOpen(): self
    {
        return new self(failed: true);
    }
}

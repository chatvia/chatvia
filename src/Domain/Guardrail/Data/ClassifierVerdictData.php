<?php

namespace Domain\Guardrail\Data;

use Domain\Guardrail\Enums\GuardSeverity;

/**
 * The parsed classifier result. `failed` is true when the classifier errored or
 * returned garbage — guards FAIL OPEN (treat as no-trigger) so a model outage
 * degrades safety rather than taking the whole chat down.
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
    ) {}

    public static function failOpen(): self
    {
        return new self(failed: true);
    }
}

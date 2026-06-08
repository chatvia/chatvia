<?php

namespace Domain\Guardrail\Data;

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
        public string $injectionRisk = 'none',
        public bool $offTopic = false,
        public bool $toxic = false,
        public string $toxicitySeverity = 'none',
        public array $toxicityCategories = [],
        public ?string $reason = null,
        public bool $failed = false,
    ) {}

    public static function failOpen(): self
    {
        return new self(failed: true);
    }
}

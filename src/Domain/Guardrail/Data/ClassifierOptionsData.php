<?php

declare(strict_types=1);

namespace Domain\Guardrail\Data;

/**
 * Which LLM-classified checks to run on a piece of text, plus the topic policy.
 * PII is handled separately (regex), so it isn't here. The schema + prompt the
 * classifier builds are driven entirely by these flags.
 */
final readonly class ClassifierOptionsData
{
    /**
     * @param  list<string>  $blockedTopics
     */
    public function __construct(
        public bool $checkInjection = false,
        public bool $checkTopic = false,
        public bool $checkToxicity = false,
        public array $blockedTopics = [],
        public ?string $allowedScope = null,
    ) {}

    public function any(): bool
    {
        return $this->checkInjection || $this->checkTopic || $this->checkToxicity;
    }
}

<?php

declare(strict_types=1);

namespace Domain\Guardrail\Data;

use Domain\Guardrail\Enums\GuardType;

/**
 * Which LLM-classified checks to run on a piece of text, plus the topic policy
 * and the earlier messages of the chat (context for reading a follow-up; only
 * the text itself is judged). PII is handled separately (regex), so it isn't
 * here. The schema + prompt the classifier builds are driven by these flags.
 */
final readonly class ClassifierOptionsData
{
    /**
     * @param  list<string>  $blockedTopics  One line per topic (name, description, examples), numbered from 1 in the prompt.
     * @param  list<array{role: string, content: mixed}>  $history
     */
    public function __construct(
        public bool $checkInjection = false,
        public bool $checkTopic = false,
        public bool $checkToxicity = false,
        public array $blockedTopics = [],
        public ?string $allowedScope = null,
        public array $history = [],
    ) {}

    public function any(): bool
    {
        return $this->checkInjection || $this->checkTopic || $this->checkToxicity;
    }

    /**
     * The guards these checks stand for, e.g. to log each one as skipped when
     * the classifier fails.
     *
     * @return list<GuardType>
     */
    public function guardTypes(): array
    {
        return array_values(array_filter([
            $this->checkInjection ? GuardType::PromptInjection : null,
            $this->checkTopic ? GuardType::BlockedTopic : null,
            $this->checkToxicity ? GuardType::Toxicity : null,
        ]));
    }
}

<?php

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Enums\ScorerType;

/**
 * Resolves the {@see ScorerInterface} for a given {@see ScorerType}. The
 * concrete scorers are container-resolved (the judge gets its provider
 * injected), so a single `for()` call returns a ready-to-use scorer.
 */
class ScorerRegistry
{
    public function __construct(
        private readonly ContainsScorer $contains,
        private readonly RegexScorer $regex,
        private readonly ExactMatchScorer $exact,
        private readonly JsonSchemaScorer $jsonSchema,
        private readonly LlmJudgeScorer $llmJudge,
        private readonly ToolCallScorer $toolCall,
    ) {}

    public function for(ScorerType $type): ScorerInterface
    {
        return match ($type) {
            ScorerType::Contains => $this->contains,
            ScorerType::Regex => $this->regex,
            ScorerType::Exact => $this->exact,
            ScorerType::JsonSchema => $this->jsonSchema,
            ScorerType::LlmJudge => $this->llmJudge,
            ScorerType::ToolCall => $this->toolCall,
        };
    }
}

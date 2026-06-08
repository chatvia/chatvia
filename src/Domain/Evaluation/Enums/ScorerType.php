<?php

namespace Domain\Evaluation\Enums;

/**
 * How an eval case's answer is scored. The first four are deterministic
 * (free + instant); `LlmJudge` asks an open-weight model for a constrained
 * pass/fail verdict.
 */
enum ScorerType: string
{
    case Contains = 'contains';
    case Regex = 'regex';
    case Exact = 'exact';
    case JsonSchema = 'json_schema';
    case LlmJudge = 'llm_judge';
}

<?php

declare(strict_types=1);

namespace Domain\Evaluation\Enums;

/**
 * How an eval case's answer is scored. The first four are deterministic
 * (free + instant); `LlmJudge` asks an open-weight model for a constrained
 * pass/fail verdict. `ToolCall` is different in kind: it grades the agent's
 * INTENDED tool call (name + arguments) rather than any text answer — the eval
 * never executes the tool, so it costs nothing and has no side effects.
 */
enum ScorerType: string
{
    case Contains = 'contains';
    case Regex = 'regex';
    case Exact = 'exact';
    case JsonSchema = 'json_schema';
    case LlmJudge = 'llm_judge';
    case ToolCall = 'tool_call';
}

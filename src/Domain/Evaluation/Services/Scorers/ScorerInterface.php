<?php

declare(strict_types=1);

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;

/**
 * Scores one eval case against the captured agent run. Implementations read the
 * case's `scorer_config` / `expected_output` for what to check against. Text
 * scorers look at `$result->answer`; the tool_call scorer looks at
 * `$result->toolCalls`.
 */
interface ScorerInterface
{
    public function score(EvalCase $case, AgentRunResultData $result): ScoreResultData;
}

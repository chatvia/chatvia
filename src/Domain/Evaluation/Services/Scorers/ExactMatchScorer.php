<?php

declare(strict_types=1);

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Contracts\Scorer;
use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;
use Illuminate\Support\Str;

/**
 * Passes only when the agent's answer equals `expected_output` exactly (both
 * sides trimmed). The strictest scorer — for deterministic, canned replies.
 */
class ExactMatchScorer implements Scorer
{
    public function score(EvalCase $case, AgentRunResultData $result): ScoreResultData
    {
        $expected = Str::trim($case->expected_output ?? '');
        $actual = $result->answer;
        $got = Str::trim($actual);

        return $got === $expected
            ? ScoreResultData::pass(1.0, 'Output exactly matches the expected answer.')
            : ScoreResultData::fail(0.0, 'Output does not exactly match the expected answer.');
    }
}

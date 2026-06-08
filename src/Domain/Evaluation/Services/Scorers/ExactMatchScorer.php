<?php

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;

/**
 * Passes only when the agent's answer equals `expected_output` exactly (both
 * sides trimmed). The strictest scorer — for deterministic, canned replies.
 */
class ExactMatchScorer implements ScorerInterface
{
    public function score(EvalCase $case, string $actual): ScoreResultData
    {
        $expected = trim((string) ($case->expected_output ?? ''));
        $got = trim($actual);

        return $got === $expected
            ? ScoreResultData::pass(1.0, 'Output exactly matches the expected answer.')
            : ScoreResultData::fail(0.0, 'Output does not exactly match the expected answer.');
    }
}

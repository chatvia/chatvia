<?php

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;

/**
 * Passes when the agent's answer contains an expected substring. The needle is
 * `scorer_config.value` (falling back to `expected_output`); matching is
 * case-insensitive unless `scorer_config.case_sensitive` is true.
 */
class ContainsScorer implements ScorerInterface
{
    public function score(EvalCase $case, AgentRunResultData $result): ScoreResultData
    {
        $actual = $result->answer;
        $needle = (string) ($case->scorer_config['value'] ?? $case->expected_output ?? '');

        if ($needle === '') {
            return ScoreResultData::fail(0.0, 'No expected substring configured.');
        }

        $caseSensitive = (bool) ($case->scorer_config['case_sensitive'] ?? false);

        $hit = $caseSensitive
            ? str_contains($actual, $needle)
            : str_contains(mb_strtolower($actual), mb_strtolower($needle));

        return $hit
            ? ScoreResultData::pass(1.0, "Output contains \"{$needle}\".")
            : ScoreResultData::fail(0.0, "Output does not contain \"{$needle}\".");
    }
}

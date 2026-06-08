<?php

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;

/**
 * Passes when the agent's answer matches the `scorer_config.pattern` regex.
 * An invalid pattern fails the case rather than throwing.
 */
class RegexScorer implements ScorerInterface
{
    public function score(EvalCase $case, string $actual): ScoreResultData
    {
        $pattern = (string) ($case->scorer_config['pattern'] ?? '');

        if ($pattern === '') {
            return ScoreResultData::fail(0.0, 'No regex pattern configured.');
        }

        $matched = @preg_match($pattern, $actual);

        if ($matched === false) {
            return ScoreResultData::fail(0.0, 'Invalid regex pattern.');
        }

        return $matched === 1
            ? ScoreResultData::pass(1.0, 'Output matches the pattern.')
            : ScoreResultData::fail(0.0, 'Output does not match the pattern.');
    }
}

<?php

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;

/**
 * Scores an agent's actual answer for one eval case. Implementations read the
 * case's `scorer_config` / `expected_output` for what to check against.
 */
interface ScorerInterface
{
    public function score(EvalCase $case, string $actual): ScoreResultData;
}

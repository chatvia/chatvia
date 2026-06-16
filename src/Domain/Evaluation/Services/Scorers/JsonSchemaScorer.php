<?php

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;

/**
 * Passes when the agent's answer is valid JSON containing every key listed in
 * `scorer_config.required`. A pragmatic structural check (not full JSON Schema
 * validation) — enough to gate "did the agent return the expected shape?".
 */
class JsonSchemaScorer implements ScorerInterface
{
    public function score(EvalCase $case, AgentRunResultData $result): ScoreResultData
    {
        $decoded = json_decode(trim($result->answer), true);

        if (! is_array($decoded)) {
            return ScoreResultData::fail(0.0, 'Output is not valid JSON.');
        }

        $required = array_map('strval', (array) ($case->scorer_config['required'] ?? []));
        $missing = array_values(array_filter(
            $required,
            fn (string $key): bool => ! array_key_exists($key, $decoded),
        ));

        return $missing === []
            ? ScoreResultData::pass(1.0, 'Valid JSON with all required keys.')
            : ScoreResultData::fail(0.0, 'Missing required keys: '.implode(', ', $missing));
    }
}

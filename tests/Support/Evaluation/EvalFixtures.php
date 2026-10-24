<?php

declare(strict_types=1);

namespace Tests\Support\Evaluation;

use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;

/**
 * Unsaved eval cases and captured agent runs for the scorers and the scoring
 * Action. A case is assigned through its casts, so scorer_type and
 * scorer_config arrive as production reads them.
 */
final class EvalFixtures
{
    /** @param  array<string, mixed>  $config */
    public static function case(ScorerType $scorer, array $config = [], ?string $expected = null, string $input = 'prompt'): EvalCase
    {
        $case = new EvalCase;
        $case->name = 'case';
        $case->input = $input;
        $case->scorer_type = $scorer;
        $case->scorer_config = $config;
        $case->expected_output = $expected;

        return $case;
    }

    /**
     * A captured run: the answer for the text scorers, and the intended tool
     * calls (each `['name' => …, 'arguments' => json]`) for the tool scorer.
     *
     * @param  list<array{name: string, arguments: string}>  $toolCalls
     * @param  list<string>  $sources
     */
    public static function run(string $answer = '', array $toolCalls = [], ?string $error = null, array $sources = [], ?int $tokens = 10, string $traceId = 'trace'): AgentRunResultData
    {
        return new AgentRunResultData(
            answer: $answer,
            sources: $sources,
            toolsCalled: array_column($toolCalls, 'name'),
            tokens: $tokens,
            latencyMs: 0,
            traceId: $traceId,
            error: $error,
            toolCalls: $toolCalls,
        );
    }
}

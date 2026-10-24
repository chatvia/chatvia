<?php

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;
use Illuminate\Support\Str;

/**
 * Grades the agent's INTENDED tool call — never executing it. The eval runner
 * captures every tool call the agent decided to make (name + raw arguments) and
 * stops before dispatch; this scorer checks the agent reached for the expected
 * tool, and (optionally) that the call carried the expected arguments.
 *
 * `scorer_config`:
 *   - `tool`      (required) the expected tool name. Matches the name as the
 *                 agent sees it, or — for an MCP tool `mcp__{server}__{tool}` —
 *                 just the trailing tool segment.
 *   - `arguments` (optional) a JSON object of arguments the call must include.
 *                 Each expected key must be present with a matching value
 *                 (scalars compared trimmed + case-insensitively; nested values
 *                 compared structurally). Extra arguments are ignored. Omit it to
 *                 grade tool selection only.
 *
 * The score is the fraction of expected arguments that matched (1.0 when only the
 * tool name is checked), and `pass` requires every expected argument to match.
 */
class ToolCallScorer implements ScorerInterface
{
    public function score(EvalCase $case, AgentRunResultData $result): ScoreResultData
    {
        $expectedTool = Str::trim((string) ($case->scorer_config['tool'] ?? ''));

        if ($expectedTool === '') {
            return ScoreResultData::fail(0.0, 'No expected tool configured.');
        }

        if ($result->toolCalls === []) {
            return ScoreResultData::fail(0.0, "The agent called no tool (expected \"{$expectedTool}\").");
        }

        $match = $this->findCall($result->toolCalls, $expectedTool);

        if ($match === null) {
            $called = implode(', ', array_map(static fn (array $c): string => $c['name'] ?? '', $result->toolCalls));

            return ScoreResultData::fail(0.0, "Expected the agent to call \"{$expectedTool}\", but it called: {$called}.");
        }

        $expectedArgs = $this->expectedArguments($case);

        if ($expectedArgs === []) {
            return ScoreResultData::pass(1.0, "Agent called \"{$expectedTool}\".");
        }

        $actualArgs = json_decode($match['arguments'] ?? '', true);

        if (! is_array($actualArgs)) {
            return ScoreResultData::fail(0.0, "Agent called \"{$expectedTool}\" but its arguments were not valid JSON.");
        }

        [$matched, $mismatches] = $this->compareArguments($expectedArgs, $actualArgs);
        $score = count($expectedArgs) > 0 ? $matched / count($expectedArgs) : 1.0;

        if ($mismatches === []) {
            return ScoreResultData::pass(1.0, "Agent called \"{$expectedTool}\" with matching arguments.");
        }

        return new ScoreResultData(false, $score, "Agent called \"{$expectedTool}\" but arguments differ: ".implode('; ', $mismatches).'.');
    }

    /**
     * The first captured call whose name matches — exactly, or by the unprefixed
     * segment of an MCP tool name (`mcp__{server}__{tool}` → `{tool}`).
     *
     * @param  list<array{name: string, arguments: string}>  $calls
     * @return array{name: string, arguments: string}|null
     */
    private function findCall(array $calls, string $expectedTool): ?array
    {
        foreach ($calls as $call) {
            $name = $call['name'] ?? '';

            if ($name === $expectedTool || Str::endsWith($name, '__'.$expectedTool)) {
                return $call;
            }
        }

        return null;
    }

    /**
     * The expected arguments object — stored as a JSON string (from the form) or
     * an already-decoded array. A non-object value yields no expectation.
     *
     * @return array<string, mixed>
     */
    private function expectedArguments(EvalCase $case): array
    {
        $raw = $case->scorer_config['arguments'] ?? null;

        if (is_scalar($raw)) {
            $raw = blank($raw) ? null : json_decode(Str::trim((string) $raw), true);
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Check each expected key against the actual arguments.
     *
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @return array{0: int, 1: list<string>} [matched count, mismatch messages]
     */
    private function compareArguments(array $expected, array $actual): array
    {
        $matched = 0;
        $mismatches = [];

        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $actual)) {
                $mismatches[] = "\"{$key}\" missing";

                continue;
            }

            if ($this->valuesMatch($value, $actual[$key])) {
                $matched++;

                continue;
            }

            $mismatches[] = "\"{$key}\" expected ".$this->stringify($value).', got '.$this->stringify($actual[$key]);
        }

        return [$matched, $mismatches];
    }

    /**
     * Lenient value match: nested values compared structurally via JSON, scalars
     * compared trimmed + case-insensitively so the agent's casing or spacing
     * doesn't fail an otherwise-correct call.
     */
    private function valuesMatch(mixed $expected, mixed $actual): bool
    {
        if (is_array($expected) || is_array($actual)) {
            return json_encode($expected) === json_encode($actual);
        }

        return Str::lower(Str::trim((string) $expected)) === Str::lower(Str::trim((string) $actual));
    }

    private function stringify(mixed $value): string
    {
        return is_array($value) ? (string) json_encode($value) : '"'.$value.'"';
    }
}

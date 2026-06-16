<?php

use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Services\Scorers\ContainsScorer;
use Domain\Evaluation\Services\Scorers\ExactMatchScorer;
use Domain\Evaluation\Services\Scorers\JsonSchemaScorer;
use Domain\Evaluation\Services\Scorers\RegexScorer;
use Domain\Evaluation\Services\Scorers\ToolCallScorer;

/**
 * The deterministic scorers are the free, instant half of the eval harness — a
 * regressed one would silently green-light wrong answers (or fail correct ones)
 * on every run. They touch no DB; we build an unsaved EvalCase and assign through
 * the casts so scorer_type/scorer_config arrive exactly as production would read
 * them. Scorers receive the captured run (answer + intended tool calls), built
 * here by runResult().
 */
function evalCase(string $scorerType, array $config = [], ?string $expected = null): EvalCase
{
    $case = new EvalCase;
    $case->name = 'case';
    $case->input = 'prompt';
    $case->scorer_type = $scorerType;
    $case->scorer_config = $config;
    $case->expected_output = $expected;

    return $case;
}

/**
 * A captured agent run for a scorer. `$answer` feeds the text scorers; `$toolCalls`
 * (each `['name' => ..., 'arguments' => json string]`) feeds the tool_call scorer.
 */
function runResult(string $answer = '', array $toolCalls = []): AgentRunResultData
{
    return new AgentRunResultData(
        answer: $answer,
        sources: [],
        toolsCalled: array_map(fn (array $c): string => $c['name'], $toolCalls),
        tokens: null,
        latencyMs: 0,
        traceId: 'trace',
        toolCalls: $toolCalls,
    );
}

describe('ContainsScorer', function (): void {
    it('passes when the answer contains the substring, case-insensitively by default', function (): void {
        $result = (new ContainsScorer)->score(evalCase('contains', ['value' => '30 Days']), runResult('Refunds within 30 days.'));

        expect($result->pass)->toBeTrue()
            ->and($result->score)->toBe(1.0);
    });

    it('fails when the substring is absent', function (): void {
        $result = (new ContainsScorer)->score(evalCase('contains', ['value' => '14 days']), runResult('Refunds within 30 days.'));

        expect($result->pass)->toBeFalse();
    });

    it('honours case sensitivity when configured', function (): void {
        $result = (new ContainsScorer)->score(
            evalCase('contains', ['value' => '30 DAYS', 'case_sensitive' => true]),
            runResult('Refunds within 30 days.'),
        );

        expect($result->pass)->toBeFalse();
    });

    it('falls back to expected_output when no value is configured', function (): void {
        $result = (new ContainsScorer)->score(evalCase('contains', [], '30 days'), runResult('Refunds within 30 days.'));

        expect($result->pass)->toBeTrue();
    });

    it('fails with a rationale when nothing is configured', function (): void {
        $result = (new ContainsScorer)->score(evalCase('contains'), runResult('anything'));

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('No expected substring');
    });
});

describe('RegexScorer', function (): void {
    it('passes on a matching pattern', function (): void {
        $result = (new RegexScorer)->score(evalCase('regex', ['pattern' => '/\b30 days\b/i']), runResult('within 30 Days'));

        expect($result->pass)->toBeTrue();
    });

    it('fails on a non-matching pattern', function (): void {
        $result = (new RegexScorer)->score(evalCase('regex', ['pattern' => '/\d{4}/']), runResult('no numbers here'));

        expect($result->pass)->toBeFalse();
    });

    it('fails safely on an invalid pattern instead of throwing', function (): void {
        $result = (new RegexScorer)->score(evalCase('regex', ['pattern' => '/(unclosed']), runResult('whatever'));

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('Invalid regex');
    });
});

describe('ExactMatchScorer', function (): void {
    it('passes on an exact (trimmed) match', function (): void {
        $result = (new ExactMatchScorer)->score(evalCase('exact', [], 'Yes.'), runResult('  Yes.  '));

        expect($result->pass)->toBeTrue();
    });

    it('fails when the answer differs', function (): void {
        $result = (new ExactMatchScorer)->score(evalCase('exact', [], 'Yes.'), runResult('Yes, absolutely.'));

        expect($result->pass)->toBeFalse();
    });
});

describe('JsonSchemaScorer', function (): void {
    it('passes valid JSON with all required keys', function (): void {
        $result = (new JsonSchemaScorer)->score(
            evalCase('json_schema', ['required' => ['name', 'email']]),
            runResult('{"name": "Ada", "email": "ada@example.com"}'),
        );

        expect($result->pass)->toBeTrue();
    });

    it('fails when a required key is missing', function (): void {
        $result = (new JsonSchemaScorer)->score(
            evalCase('json_schema', ['required' => ['name', 'email']]),
            runResult('{"name": "Ada"}'),
        );

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('email');
    });

    it('fails on non-JSON output', function (): void {
        $result = (new JsonSchemaScorer)->score(evalCase('json_schema', ['required' => []]), runResult('not json'));

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('not valid JSON');
    });
});

describe('ToolCallScorer', function (): void {
    it('passes when the agent calls the expected tool (name only)', function (): void {
        $result = (new ToolCallScorer)->score(
            evalCase('tool_call', ['tool' => 'send_email']),
            runResult('', [['name' => 'send_email', 'arguments' => '{}']]),
        );

        expect($result->pass)->toBeTrue()
            ->and($result->score)->toBe(1.0);
    });

    it('fails when the agent calls no tool', function (): void {
        $result = (new ToolCallScorer)->score(evalCase('tool_call', ['tool' => 'send_email']), runResult('Here you go.'));

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('called no tool');
    });

    it('fails when the agent calls a different tool', function (): void {
        $result = (new ToolCallScorer)->score(
            evalCase('tool_call', ['tool' => 'send_email']),
            runResult('', [['name' => 'get_weather', 'arguments' => '{}']]),
        );

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('get_weather');
    });

    it('matches an MCP tool by its unprefixed name', function (): void {
        $result = (new ToolCallScorer)->score(
            evalCase('tool_call', ['tool' => 'send_message']),
            runResult('', [['name' => 'mcp__slack__send_message', 'arguments' => '{}']]),
        );

        expect($result->pass)->toBeTrue();
    });

    it('passes when expected arguments are a subset of the call (extras ignored)', function (): void {
        $result = (new ToolCallScorer)->score(
            evalCase('tool_call', ['tool' => 'send_email', 'arguments' => '{"to": "a@b.com"}']),
            runResult('', [['name' => 'send_email', 'arguments' => '{"to": "a@b.com", "subject": "Hi"}']]),
        );

        expect($result->pass)->toBeTrue()
            ->and($result->rationale)->toContain('matching arguments');
    });

    it('matches argument values leniently (trimmed, case-insensitive)', function (): void {
        $result = (new ToolCallScorer)->score(
            evalCase('tool_call', ['tool' => 'send_email', 'arguments' => '{"to": "A@B.com"}']),
            runResult('', [['name' => 'send_email', 'arguments' => '{"to": " a@b.com "}']]),
        );

        expect($result->pass)->toBeTrue();
    });

    it('fails with a partial score when an argument value differs', function (): void {
        $result = (new ToolCallScorer)->score(
            evalCase('tool_call', ['tool' => 'send_email', 'arguments' => '{"to": "a@b.com", "subject": "Hi"}']),
            runResult('', [['name' => 'send_email', 'arguments' => '{"to": "a@b.com", "subject": "Bye"}']]),
        );

        expect($result->pass)->toBeFalse()
            ->and($result->score)->toBe(0.5)
            ->and($result->rationale)->toContain('subject');
    });

    it('fails when a required argument is missing', function (): void {
        $result = (new ToolCallScorer)->score(
            evalCase('tool_call', ['tool' => 'send_email', 'arguments' => '{"to": "a@b.com"}']),
            runResult('', [['name' => 'send_email', 'arguments' => '{"subject": "Hi"}']]),
        );

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('missing');
    });

    it('fails with a rationale when no expected tool is configured', function (): void {
        $result = (new ToolCallScorer)->score(
            evalCase('tool_call'),
            runResult('', [['name' => 'send_email', 'arguments' => '{}']]),
        );

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('No expected tool');
    });
});

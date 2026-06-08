<?php

use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Services\Scorers\ContainsScorer;
use Domain\Evaluation\Services\Scorers\ExactMatchScorer;
use Domain\Evaluation\Services\Scorers\JsonSchemaScorer;
use Domain\Evaluation\Services\Scorers\RegexScorer;

/**
 * The deterministic scorers are the free, instant half of the eval harness — a
 * regressed one would silently green-light wrong answers (or fail correct ones)
 * on every run. They touch no DB; we build an unsaved EvalCase and assign through
 * the casts so scorer_type/scorer_config arrive exactly as production would read
 * them.
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

describe('ContainsScorer', function (): void {
    it('passes when the answer contains the substring, case-insensitively by default', function (): void {
        $result = (new ContainsScorer)->score(evalCase('contains', ['value' => '30 Days']), 'Refunds within 30 days.');

        expect($result->pass)->toBeTrue()
            ->and($result->score)->toBe(1.0);
    });

    it('fails when the substring is absent', function (): void {
        $result = (new ContainsScorer)->score(evalCase('contains', ['value' => '14 days']), 'Refunds within 30 days.');

        expect($result->pass)->toBeFalse();
    });

    it('honours case sensitivity when configured', function (): void {
        $result = (new ContainsScorer)->score(
            evalCase('contains', ['value' => '30 DAYS', 'case_sensitive' => true]),
            'Refunds within 30 days.',
        );

        expect($result->pass)->toBeFalse();
    });

    it('falls back to expected_output when no value is configured', function (): void {
        $result = (new ContainsScorer)->score(evalCase('contains', [], '30 days'), 'Refunds within 30 days.');

        expect($result->pass)->toBeTrue();
    });

    it('fails with a rationale when nothing is configured', function (): void {
        $result = (new ContainsScorer)->score(evalCase('contains'), 'anything');

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('No expected substring');
    });
});

describe('RegexScorer', function (): void {
    it('passes on a matching pattern', function (): void {
        $result = (new RegexScorer)->score(evalCase('regex', ['pattern' => '/\b30 days\b/i']), 'within 30 Days');

        expect($result->pass)->toBeTrue();
    });

    it('fails on a non-matching pattern', function (): void {
        $result = (new RegexScorer)->score(evalCase('regex', ['pattern' => '/\d{4}/']), 'no numbers here');

        expect($result->pass)->toBeFalse();
    });

    it('fails safely on an invalid pattern instead of throwing', function (): void {
        $result = (new RegexScorer)->score(evalCase('regex', ['pattern' => '/(unclosed']), 'whatever');

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('Invalid regex');
    });
});

describe('ExactMatchScorer', function (): void {
    it('passes on an exact (trimmed) match', function (): void {
        $result = (new ExactMatchScorer)->score(evalCase('exact', [], 'Yes.'), '  Yes.  ');

        expect($result->pass)->toBeTrue();
    });

    it('fails when the answer differs', function (): void {
        $result = (new ExactMatchScorer)->score(evalCase('exact', [], 'Yes.'), 'Yes, absolutely.');

        expect($result->pass)->toBeFalse();
    });
});

describe('JsonSchemaScorer', function (): void {
    it('passes valid JSON with all required keys', function (): void {
        $result = (new JsonSchemaScorer)->score(
            evalCase('json_schema', ['required' => ['name', 'email']]),
            '{"name": "Ada", "email": "ada@example.com"}',
        );

        expect($result->pass)->toBeTrue();
    });

    it('fails when a required key is missing', function (): void {
        $result = (new JsonSchemaScorer)->score(
            evalCase('json_schema', ['required' => ['name', 'email']]),
            '{"name": "Ada"}',
        );

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('email');
    });

    it('fails on non-JSON output', function (): void {
        $result = (new JsonSchemaScorer)->score(evalCase('json_schema', ['required' => []]), 'not json');

        expect($result->pass)->toBeFalse()
            ->and($result->rationale)->toContain('not valid JSON');
    });
});

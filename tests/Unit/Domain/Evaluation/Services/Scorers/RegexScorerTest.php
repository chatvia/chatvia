<?php

use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Services\Scorers\RegexScorer;
use Tests\Support\Evaluation\EvalFixtures;

/**
 * The regex scorer passes an answer the configured pattern matches, and fails
 * (never throws) on a pattern that does not compile.
 */
it('passes only an answer the pattern matches', function (string $pattern, string $answer, bool $passes): void {
    $result = (new RegexScorer)->score(EvalFixtures::case(ScorerType::Regex, ['pattern' => $pattern]), EvalFixtures::run($answer));

    expect($result->pass)->toBe($passes)
        ->and($result->score)->toBe($passes ? 1.0 : 0.0);
})->with([
    'matching' => ['/\b30 days\b/i', 'within 30 Days', true],
    'not matching' => ['/\d{4}/', 'no numbers here', false],
]);

it('fails safely on an invalid pattern instead of throwing', function (): void {
    $result = (new RegexScorer)->score(EvalFixtures::case(ScorerType::Regex, ['pattern' => '/(unclosed']), EvalFixtures::run('whatever'));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain('Invalid regex');
});

<?php

use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Services\Scorers\RegexScorer;
use Tests\Support\Evaluation\EvalFixtures;

/**
 * The regex scorer fails (never throws) on a pattern that does not compile.
 */
it('fails safely on an invalid pattern instead of throwing', function (): void {
    $result = (new RegexScorer)->score(EvalFixtures::case(ScorerType::Regex, ['pattern' => '/(unclosed']), EvalFixtures::run('whatever'));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain('Invalid regex');
});

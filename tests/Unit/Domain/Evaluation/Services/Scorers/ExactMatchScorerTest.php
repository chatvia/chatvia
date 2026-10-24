<?php

use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Services\Scorers\ExactMatchScorer;
use Tests\Support\Evaluation\EvalFixtures;

/**
 * The exact-match scorer passes an answer equal to the expected output once
 * both are trimmed.
 */
it('passes only an answer equal to the expected output', function (string $answer, bool $passes): void {
    $result = (new ExactMatchScorer)->score(EvalFixtures::case(ScorerType::Exact, [], 'Yes.'), EvalFixtures::run($answer));

    expect($result->pass)->toBe($passes)
        ->and($result->score)->toBe($passes ? 1.0 : 0.0);
})->with([
    'equal once trimmed' => ['  Yes.  ', true],
    'different' => ['Yes, absolutely.', false],
]);

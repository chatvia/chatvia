<?php

use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Services\Scorers\ContainsScorer;
use Tests\Support\Evaluation\EvalFixtures;

/**
 * The contains scorer passes an answer holding the configured substring (or
 * the case's expected output), case-insensitively unless told otherwise. A
 * broken one would green-light wrong answers on every run.
 */
it('passes only an answer that holds the substring', function (array $config, ?string $expected, bool $passes): void {
    $result = (new ContainsScorer)->score(EvalFixtures::case(ScorerType::Contains, $config, $expected), EvalFixtures::run('Refunds within 30 days.'));

    expect($result->pass)->toBe($passes)
        ->and($result->score)->toBe($passes ? 1.0 : 0.0);
})->with([
    'in another case, by default' => [['value' => '30 Days'], null, true],
    'absent' => [['value' => '14 days'], null, false],
    'in another case, with case sensitivity on' => [['value' => '30 DAYS', 'case_sensitive' => true], null, false],
    'the expected output, when no value is set' => [[], '30 days', true],
]);

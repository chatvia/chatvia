<?php

use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Services\Scorers\JsonSchemaScorer;
use Tests\Support\Evaluation\EvalFixtures;

/**
 * The JSON scorer fails an answer that is not valid JSON or lacks a required
 * key, and names what is wrong.
 */
it('fails an answer that is not JSON or lacks a required key, naming why', function (array $required, string $answer, string $rationale): void {
    $result = (new JsonSchemaScorer)->score(EvalFixtures::case(ScorerType::JsonSchema, ['required' => $required]), EvalFixtures::run($answer));

    expect($result->pass)->toBeFalse()
        ->and($result->score)->toBe(0.0)
        ->and($result->rationale)->toContain($rationale);
})->with([
    'a required key is missing' => [['name', 'email'], '{"name": "Ada"}', 'email'],
    'not JSON' => [[], 'not json', 'not valid JSON'],
]);

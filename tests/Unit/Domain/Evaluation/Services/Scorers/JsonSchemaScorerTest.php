<?php

use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Services\Scorers\JsonSchemaScorer;
use Tests\Support\Evaluation\EvalFixtures;

/**
 * The JSON scorer passes an answer that is valid JSON holding every required
 * key, and names what is wrong otherwise.
 */
it('passes valid JSON with all required keys', function (): void {
    $result = (new JsonSchemaScorer)->score(
        EvalFixtures::case(ScorerType::JsonSchema, ['required' => ['name', 'email']]),
        EvalFixtures::run('{"name": "Ada", "email": "ada@example.com"}'),
    );

    expect($result->pass)->toBeTrue()
        ->and($result->score)->toBe(1.0);
});

it('fails and says why', function (array $required, string $answer, string $rationale): void {
    $result = (new JsonSchemaScorer)->score(EvalFixtures::case(ScorerType::JsonSchema, ['required' => $required]), EvalFixtures::run($answer));

    expect($result->pass)->toBeFalse()
        ->and($result->score)->toBe(0.0)
        ->and($result->rationale)->toContain($rationale);
})->with([
    'a required key is missing' => [['name', 'email'], '{"name": "Ada"}', 'email'],
    'not JSON' => [[], 'not json', 'not valid JSON'],
]);
